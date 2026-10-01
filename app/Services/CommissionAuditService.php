<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CommissionLedger;
use App\Models\CommissionPlan;
use App\Models\CommissionRule;
use App\Models\Membership;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\ReportingLine;
use App\Models\Sale;
use App\Models\SalesProspect;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Revisa que una venta esté registrada, pagada y con sus comisiones, y repara lo que falta.
 * Las reglas de cálculo reproducen las de PaymentConfirmationService, pero resolviendo
 * cadena y regla a la fecha de cada pago (no a la de hoy).
 */
class CommissionAuditService
{
    private const PRE_SALE_STATUSES = ['nuevo', 'calificado', 'demo_programada', 'demo_realizada', 'propuesta_enviada', 'pago_pendiente'];
    private const ROLE_LABELS = ['manager' => 'gerente', 'supervisor' => 'supervisor', 'seller' => 'vendedor'];
    private const TYPE_LABELS = ['activation' => 'activación', 'recurring' => 'recurrente'];

    /** @var list<array{section: string, level: string, message: string}> */
    private array $findings = [];
    private int $repairs = 0;
    private bool $repair = false;
    private ?Membership $actor = null;
    private string $tag = '';
    private array $names = [];

    public function __construct(private readonly ProspectStatusService $statusService) {}

    /**
     * @return array{repair: bool, findings: list<array{section: string, level: string, message: string}>, repairs: int, errors: int, warnings: int}
     */
    public function review(SalesProspect $prospect, bool $repair, ?Membership $actor = null): array
    {
        $this->findings = [];
        $this->repairs = 0;
        $this->repair = $repair;
        $this->actor = $actor;
        $this->names = [];

        $work = function () use ($prospect, $repair, $actor) {
            $opportunities = $prospect->opportunities()
                ->with('subscriptionPlan')
                ->orderBy('id')
                ->get()
                ->filter(fn (Opportunity $o) => Subscription::where('opportunity_id', $o->id)->exists()
                    || Payment::where('opportunity_id', $o->id)->exists()
                    || Sale::where('opportunity_id', $o->id)->exists())
                ->values();

            if ($opportunities->isEmpty()) {
                $this->add('sold', 'info', 'El prospecto no tiene suscripción, pagos ni venta: no hay nada que revisar.');

                return;
            }

            foreach ($opportunities as $opportunity) {
                $this->tag = $opportunities->count() > 1 ? '['.($opportunity->subscriptionPlan?->name ?? 'sin plan').'] ' : '';
                $this->auditOpportunity($prospect, $opportunity);
            }

            if ($repair && $this->repairs > 0) {
                AuditLog::record(
                    actor: $actor,
                    action: 'commission.audit_repaired',
                    subject: $prospect,
                    before: [],
                    after: [
                        'repairs' => $this->repairs,
                        'actions' => collect($this->findings)->where('level', 'repaired')->pluck('message')->values()->all(),
                    ],
                );
            }
        };

        $repair ? DB::transaction($work) : $work();

        return [
            'repair' => $repair,
            'findings' => $this->findings,
            'repairs' => $this->repairs,
            'errors' => count(array_filter($this->findings, fn ($f) => $f['level'] === 'error')),
            'warnings' => count(array_filter($this->findings, fn ($f) => $f['level'] === 'warning')),
        ];
    }

    private function auditOpportunity(SalesProspect $prospect, Opportunity $opportunity): void
    {
        $subscription = Subscription::where('opportunity_id', $opportunity->id)->orderByDesc('id')->first();

        $payments = Payment::where('opportunity_id', $opportunity->id)
            ->whereIn('status', ['confirmed', 'refunded'])
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get()
            ->values();

        $sale = Sale::where('opportunity_id', $opportunity->id)->orderBy('id')->first();
        $first = $payments->first();

        // ---- Pago ----
        if ($payments->isEmpty()) {
            $this->add('paid', 'warning', 'No hay ningún pago confirmado registrado.');
            $this->webhookHints($opportunity, $subscription);
            $this->add('sold', $sale ? 'warning' : 'info', $sale
                ? 'Existe una venta pero ningún pago confirmado que la respalde.'
                : 'La venta aún no se concreta: no hay pago confirmado.');

            return;
        }

        $refunded = $payments->where('status', 'refunded')->count();
        $this->add('paid', 'ok', sprintf(
            '%d pago(s) registrado(s)%s. Primer pago: %s.',
            $payments->count(),
            $refunded ? " ({$refunded} reembolsado(s))" : '',
            $this->money($first->amount_cents, $first->currency),
        ));

        if (! $subscription) {
            $this->add('paid', 'warning', 'Hay pagos pero no existe una suscripción ligada a la oportunidad.');
        } elseif ($subscription->status === 'pending_payment' && $payments->last()->status === 'confirmed') {
            $this->fix(
                'paid',
                'La suscripción sigue en "pago pendiente" aunque ya hay un pago confirmado.',
                'La suscripción se marcó como activa.',
                fn () => $subscription->update(['status' => 'active']),
            );
        } else {
            $this->add('paid', 'ok', "Estado de la suscripción: {$subscription->status}.");
        }

        if (! $subscription || $subscription->status === 'pending_payment') {
            $this->webhookHints($opportunity, $subscription);
        }

        $quote = $opportunity->quotes()->orderByDesc('version')->first();
        if ($quote && (int) $quote->amount_cents !== (int) $first->amount_cents) {
            $this->add('paid', 'info', sprintf(
                'El primer pago (%s) no coincide con la última cotización (%s).',
                $this->money($first->amount_cents, $first->currency),
                $this->money($quote->amount_cents, $quote->currency ?? $first->currency),
            ));
        }

        // ---- Venta ----
        $seller = Membership::withTrashed()->find($sale?->seller_membership_id ?? $prospect->owner_membership_id);

        if (! $sale) {
            if (! $seller) {
                $this->add('sold', 'error', 'Hay un pago confirmado pero no existe la venta, y el prospecto no tiene vendedor al que asignarla.');

                return;
            }

            $sale = $this->fix(
                'sold',
                'Hay un pago confirmado pero no existe la venta.',
                'Se creó la venta a partir del primer pago confirmado.',
                fn () => $seller->sales()->create([
                    'opportunity_id' => $opportunity->id,
                    'payment_id' => $first->id,
                    'amount_cents' => $first->amount_cents,
                    'currency' => $first->currency,
                    'validated_at' => $first->confirmed_at ?? now(),
                    'status' => $first->status === 'refunded' ? 'reembolsado' : 'venta_ganada',
                ]),
            );

            if ($this->repair && ! $sale) {
                return; // no se pudo crear; no tiene sentido seguir con comisiones
            }
        } else {
            $this->add('sold', 'ok', sprintf('Venta registrada: %s, estado "%s".', $this->money($sale->amount_cents, $sale->currency), $sale->status));

            if ($sale->payment_id === null) {
                $this->fix('sold', 'La venta no está ligada a ningún pago.', 'La venta se ligó al primer pago confirmado.', fn () => $sale->update(['payment_id' => $first->id]));
            }

            if ($first->status === 'refunded' && $sale->status !== 'reembolsado') {
                $this->fix('sold', 'El primer pago está reembolsado pero la venta no.', 'La venta se marcó como reembolsada.', fn () => $sale->update(['status' => 'reembolsado']));
            } elseif ($first->status === 'confirmed' && $sale->status !== 'venta_ganada') {
                $this->add('sold', 'warning', "El primer pago está confirmado pero la venta figura como \"{$sale->status}\". Requiere revisión manual.");
            }

            if ((int) $sale->amount_cents !== (int) $first->amount_cents) {
                $this->add('sold', 'info', sprintf('El monto de la venta (%s) difiere del primer pago (%s).', $this->money($sale->amount_cents, $sale->currency), $this->money($first->amount_cents, $first->currency)));
            }
        }

        if ($first->status === 'confirmed' && ! in_array($prospect->status, SalesStatsService::WON, true)) {
            if (in_array($prospect->status, self::PRE_SALE_STATUSES, true)) {
                $this->fix(
                    'sold',
                    "El prospecto sigue en \"{$prospect->status}\" aunque la venta está pagada.",
                    'El prospecto se movió a "venta ganada".',
                    fn () => $this->promoteProspect($prospect),
                );
            } else {
                $this->add('sold', 'warning', "El prospecto está en \"{$prospect->status}\" aunque hay un pago confirmado. Requiere revisión manual.");
            }
        }

        // ---- Comisiones ----
        if (! $seller) {
            $this->add('commissions', 'error', 'No se puede revisar la cadena de comisiones: no hay vendedor.');

            return;
        }

        if (! $opportunity->plan_id) {
            $this->add('commissions', 'warning', 'La oportunidad no tiene plan asignado: no se pueden resolver reglas de comisión.');

            return;
        }

        $this->auditCommissions($opportunity, $payments, $sale, $seller);
    }

    private function auditCommissions(Opportunity $opportunity, Collection $payments, ?Sale $sale, Membership $seller): void
    {
        $problems = 0;
        $expectedCount = 0;
        $withoutRule = [];

        foreach ($payments as $index => $payment) {
            $at = $payment->confirmed_at ?? now();

            $entries = CommissionLedger::where('payment_id', $payment->id)->get();
            $reversedIds = $entries->whereNotNull('reverses_ledger_id')->pluck('reverses_ledger_id')->all();
            $originals = $entries->whereNull('reverses_ledger_id');
            $activeOriginals = $originals->reject(fn ($e) => in_array($e->id, $reversedIds, true));

            // Pago reembolsado: todo asiento original debe estar revertido.
            if ($payment->status === 'refunded') {
                foreach ($activeOriginals as $entry) {
                    $problems++;
                    $label = $this->entryLabel($entry);
                    $this->fix(
                        'commissions',
                        "El pago está reembolsado pero la {$label} no se revirtió.",
                        "Se revirtió la {$label}.",
                        fn () => $this->reverseEntry($entry, 'Pago reembolsado: asiento sin revertir (revisión de comisiones).'),
                    );
                }

                continue;
            }

            $expectedKeys = [];

            foreach ($this->expectedEntries($seller, $opportunity, $payment, $index, $at, $withoutRule) as $spec) {
                $expectedCount++;
                $key = $spec['membership']->id.'|'.$spec['type'];
                $expectedKeys[] = $key;
                $label = sprintf(
                    'comisión %s de %s',
                    self::TYPE_LABELS[$spec['type']],
                    $this->member($spec['membership']->id),
                );

                $group = $originals->filter(fn ($e) => ($e->membership_id.'|'.$e->entry_type) === $key);
                $active = $group->reject(fn ($e) => in_array($e->id, $reversedIds, true))->sortBy('id')->values();

                if ($group->isEmpty()) {
                    $problems++;
                    $this->fix(
                        'commissions',
                        sprintf('Falta la %s por %s.', $label, $this->money($spec['amount'], $payment->currency)),
                        sprintf('Se generó la %s por %s.', $label, $this->money($spec['amount'], $payment->currency)),
                        fn () => $this->createEntry($sale, $payment, $spec),
                    );

                    continue;
                }

                if ($active->count() > 1) {
                    foreach ($active->slice(1) as $extra) {
                        $problems++;
                        $this->fix(
                            'commissions',
                            "La {$label} está duplicada.",
                            "Se revirtió el duplicado de la {$label}.",
                            fn () => $this->reverseEntry($extra, 'Asiento duplicado (revisión de comisiones).'),
                        );
                    }

                    continue;
                }

                if ($active->isEmpty()) {
                    $this->add('commissions', 'info', "La {$label} ya estaba revertida; no se vuelve a generar.");

                    continue;
                }

                if ((int) $active->first()->amount_cents !== $spec['amount']) {
                    $problems++;
                    $this->add('commissions', 'warning', sprintf(
                        'La %s está registrada por %s y la regla vigente da %s. Requiere ajuste manual.',
                        $label,
                        $this->money($active->first()->amount_cents, $active->first()->currency),
                        $this->money($spec['amount'], $payment->currency),
                    ));
                }
            }

            foreach ($activeOriginals as $entry) {
                if (! in_array($entry->membership_id.'|'.$entry->entry_type, $expectedKeys, true)) {
                    $problems++;
                    $this->add('commissions', 'warning', sprintf(
                        'Hay una %s por %s que no corresponde a la cadena ni a las reglas vigentes. Requiere revisión manual.',
                        $this->entryLabel($entry),
                        $this->money($entry->amount_cents, $entry->currency),
                    ));
                }
            }
        }

        if ($withoutRule !== []) {
            $this->add(
                'commissions',
                $expectedCount === 0 ? 'warning' : 'info',
                sprintf(
                    'No hay regla de comisión vigente para el plan "%s" para: %s.%s',
                    $opportunity->subscriptionPlan?->name ?? $opportunity->plan_id,
                    implode(', ', array_keys($withoutRule)),
                    $expectedCount === 0 ? ' Por eso esta venta no generó ninguna comisión.' : ' Esos eslabones no generan comisión.',
                ),
            );
        }

        if ($problems === 0 && $expectedCount > 0) {
            $this->add('commissions', 'ok', "Comisiones correctas: {$expectedCount} asiento(s) esperado(s), todos presentes.");
        }
    }

    /**
     * @param  array<string, bool>  $withoutRule  se llena por referencia con los roles sin regla
     * @return list<array<string, mixed>>
     */
    private function expectedEntries(Membership $seller, Opportunity $opportunity, Payment $payment, int $index, CarbonInterface $at, array &$withoutRule): array
    {
        $specs = [];

        foreach ($this->chainAsOf($seller, $at) as $membership) {
            [$rule, $commissionPlan] = $this->resolveRule($membership, $opportunity->plan_id, $at);

            if (! $rule) {
                $withoutRule[self::ROLE_LABELS[$membership->role] ?? $membership->role] = true;

                continue;
            }

            if ($index === 0) {
                if ($rule->activation_amount_cents === null) {
                    continue;
                }

                $specs[] = [
                    'membership' => $membership,
                    'type' => 'activation',
                    'amount' => (int) $rule->activation_amount_cents,
                    'rule' => $rule,
                    'commissionPlan' => $commissionPlan,
                    'month' => null,
                ];

                continue;
            }

            if (! $rule->recurring_percentage || ! $rule->duration_months || $index > $rule->duration_months) {
                continue;
            }

            $specs[] = [
                'membership' => $membership,
                'type' => 'recurring',
                'amount' => (int) round($payment->amount_cents * ((float) $rule->recurring_percentage / 100)),
                'rule' => $rule,
                'commissionPlan' => $commissionPlan,
                'month' => $index,
            ];
        }

        return $specs;
    }

    /** @return array{0: ?CommissionRule, 1: ?CommissionPlan} */
    private function resolveRule(Membership $membership, ?int $planId, CarbonInterface $at): array
    {
        if (! $planId) {
            return [null, null];
        }

        $commissionPlan = CommissionPlan::query()
            ->where('market_id', $membership->market_id)
            ->where('role', $membership->role)
            ->effectiveOn($at->toDateString())
            ->orderByDesc('version')
            ->first();

        if (! $commissionPlan) {
            return [null, null];
        }

        $rule = $commissionPlan->rules()->where('plan_id', $planId)->first();

        return [$rule, $rule ? $commissionPlan : null];
    }

    /**
     * La cadena vendedor → supervisor → gerente vigente en una fecha, según el historial
     * de reporting_lines (incluye membresías que ya se retiraron).
     *
     * @return list<Membership>
     */
    private function chainAsOf(Membership $seller, CarbonInterface $at): array
    {
        $chain = [$seller];
        $seen = [$seller->id => true];
        $current = $seller;

        while (true) {
            $line = ReportingLine::query()
                ->where('member_membership_id', $current->id)
                ->where('started_at', '<=', $at)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $at))
                ->orderByDesc('started_at')
                ->first();

            $manager = $line ? Membership::withTrashed()->find($line->manager_membership_id) : null;

            if (! $manager || isset($seen[$manager->id])) {
                break;
            }

            $chain[] = $manager;
            $seen[$manager->id] = true;
            $current = $manager;
        }

        return $chain;
    }

    private function createEntry(Sale $sale, Payment $payment, array $spec): CommissionLedger
    {
        /** @var CommissionRule $rule */
        $rule = $spec['rule'];
        /** @var Membership $membership */
        $membership = $spec['membership'];

        $snapshot = [
            'commission_plan_id' => $rule->commission_plan_id,
            'commission_plan_version' => $spec['commissionPlan']->version,
            'rule_id' => $rule->id,
            'role' => $membership->role,
        ];

        if ($spec['type'] === 'activation') {
            $snapshot += [
                'activation_amount_cents' => $rule->activation_amount_cents,
                'sale_amount_cents' => $sale->amount_cents,
            ];
        } else {
            $snapshot += [
                'recurring_percentage' => $rule->recurring_percentage,
                'month_number' => $spec['month'],
                'duration_months' => $rule->duration_months,
                'payment_amount_cents' => $payment->amount_cents,
            ];
        }

        $snapshot += ['resolved_at' => now()->toIso8601String(), 'repaired_by_audit' => true];

        return CommissionLedger::create([
            'membership_id' => $membership->id,
            'sale_id' => $sale->id,
            'payment_id' => $payment->id,
            'commission_rule_id' => $rule->id,
            'entry_type' => $spec['type'],
            'status' => 'pending',
            'amount_cents' => $spec['amount'],
            'currency' => $spec['type'] === 'activation' ? $sale->currency : $payment->currency,
            'calculation_snapshot' => $snapshot,
            'note' => 'Generado por la revisión de comisiones.',
            'created_by_membership_id' => $this->actor?->id,
        ]);
    }

    private function reverseEntry(CommissionLedger $entry, string $reason): CommissionLedger
    {
        return CommissionLedger::create([
            'membership_id' => $entry->membership_id,
            'sale_id' => $entry->sale_id,
            'payment_id' => $entry->payment_id,
            'commission_rule_id' => $entry->commission_rule_id,
            'entry_type' => $entry->entry_type,
            'status' => 'reversed',
            'amount_cents' => -$entry->amount_cents,
            'currency' => $entry->currency,
            'calculation_snapshot' => [
                'reverses_entry_id' => $entry->id,
                'original_amount_cents' => $entry->amount_cents,
                'reason' => $reason,
                'reversed_at' => now()->toIso8601String(),
                'repaired_by_audit' => true,
            ],
            'reverses_ledger_id' => $entry->id,
            'note' => $reason,
            'created_by_membership_id' => $this->actor?->id,
        ]);
    }

    private function promoteProspect(SalesProspect $prospect): void
    {
        if ($prospect->status === 'propuesta_enviada') {
            $prospect = $this->statusService->transitionBySystem(
                prospect: $prospect,
                toStatus: 'pago_pendiente',
                note: 'Pago recibido, en verificación (revisión de comisiones).',
            );
        }

        $this->statusService->transitionBySystem(
            prospect: $prospect,
            toStatus: 'venta_ganada',
            note: 'Pago confirmado (revisión de comisiones).',
        );
    }

    /** Eventos de webhook fallidos que mencionan esta oportunidad o su suscripción de Stripe. */
    private function webhookHints(Opportunity $opportunity, ?Subscription $subscription): void
    {
        $needles = array_filter([$opportunity->uuid, $subscription?->stripe_subscription_id]);

        $events = DB::table('webhook_events')
            ->whereNull('deleted_at')
            ->whereIn('status', ['failed', 'ignored'])
            ->where(function ($q) use ($needles) {
                foreach ($needles as $needle) {
                    $q->orWhere('payload', 'like', '%'.$needle.'%');
                }
            })
            ->orderByDesc('id')
            ->limit(3)
            ->get(['event_type', 'status', 'note']);

        foreach ($events as $event) {
            $this->add('paid', 'warning', sprintf(
                'Webhook %s relacionado: %s%s.',
                $event->status === 'failed' ? 'fallido' : 'ignorado',
                $event->event_type,
                $event->note ? " ({$event->note})" : '',
            ));
        }
    }

    // ---- utilidades ----

    private function add(string $section, string $level, string $message): void
    {
        $this->findings[] = ['section' => $section, 'level' => $level, 'message' => $this->tag.$message];
    }

    /**
     * Si hay que reparar, aplica el arreglo; si no (revisión sin cambios), solo reporta el problema.
     */
    private function fix(string $section, string $problem, string $fixed, callable $apply): mixed
    {
        if (! $this->repair) {
            $this->add($section, 'error', $problem.' (reparable)');

            return null;
        }

        try {
            $result = $apply();
        } catch (RuntimeException $e) {
            $this->add($section, 'warning', "No se pudo reparar: {$problem} {$e->getMessage()}");

            return null;
        }

        $this->repairs++;
        $this->add($section, 'repaired', $fixed);

        return $result;
    }

    private function member(int $membershipId): string
    {
        if (! isset($this->names[$membershipId])) {
            $membership = Membership::withTrashed()->with('user:id,name')->find($membershipId);

            $this->names[$membershipId] = $membership
                ? ($membership->user?->name ?? 'Sin nombre').' ('.(self::ROLE_LABELS[$membership->role] ?? $membership->role).')'
                : "membresía #{$membershipId}";
        }

        return $this->names[$membershipId];
    }

    private function entryLabel(CommissionLedger $entry): string
    {
        return sprintf(
            'comisión %s de %s por %s',
            self::TYPE_LABELS[$entry->entry_type] ?? $entry->entry_type,
            $this->member($entry->membership_id),
            $this->money($entry->amount_cents, $entry->currency),
        );
    }

    private function money(int|string|null $cents, ?string $currency): string
    {
        return number_format(((int) $cents) / 100, 2).' '.$currency;
    }
}