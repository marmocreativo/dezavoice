<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CommissionLedger;
use App\Models\CommissionPayout;
use App\Models\CommissionPayoutEntry;
use App\Models\Membership;
use App\Models\Notification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommissionPayoutService
{
    /**
     * Comisiones pendientes de pagar, agrupadas por persona y moneda, separando lo que ya se
     * puede pagar de lo que sigue en periodo de espera.
     *
     * @param  array{q?: string, market?: string, role?: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function overview(array $filters = []): Collection
    {
        $now = now();

        $entries = $this->eligibleQuery()
            ->with([
                'membership' => fn ($q) => $q->withTrashed()->with(['user:id,name,email', 'market:id,code,name']),
                'payment:id,uuid,confirmed_at',
                'commissionRule' => fn ($q) => $q->withTrashed(),
                'sale.opportunity.prospect:id,uuid,business_name',
            ])
            ->when(($filters['role'] ?? '') !== '', fn ($q) => $q->whereHas('membership', fn ($m) => $m->withTrashed()->where('role', $filters['role'])))
            ->when(($filters['market'] ?? '') !== '', fn ($q) => $q->whereHas('membership.market', fn ($m) => $m->where('uuid', $filters['market'])))
            ->when(($filters['q'] ?? '') !== '', function ($q) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $q->whereHas('membership.user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->get()
            ->filter(fn (CommissionLedger $entry) => $entry->membership !== null);

        return $entries
            ->groupBy(fn (CommissionLedger $entry) => $entry->membership_id.'|'.$entry->currency)
            ->map(function (Collection $group) use ($now) {
                $rows = $group
                    ->map(fn (CommissionLedger $entry) => ['entry' => $entry, 'available_on' => $this->availableOn($entry)])
                    ->sortBy(fn (array $row) => $row['available_on']->timestamp)
                    ->values();

                $payable = $rows->filter(fn (array $row) => $row['available_on']->lte($now))->values();
                $waiting = $rows->reject(fn (array $row) => $row['available_on']->lte($now))->values();

                return [
                    'membership' => $group->first()->membership,
                    'currency' => $group->first()->currency,
                    'payable' => $payable,
                    'waiting' => $waiting,
                    'payable_cents' => (int) $payable->sum(fn (array $row) => $row['entry']->amount_cents),
                    'waiting_cents' => (int) $waiting->sum(fn (array $row) => $row['entry']->amount_cents),
                ];
            })
            ->sortByDesc('payable_cents')
            ->values();
    }

    /**
     * Registra el pago de las comisiones elegidas. Se revalida todo dentro de una transacción,
     * por si la pantalla estaba desactualizada.
     *
     * @param  list<string>  $entryUuids
     */
    public function markPaid(
        Membership $beneficiary,
        string $currency,
        array $entryUuids,
        CarbonInterface $paidAt,
        ?string $method,
        ?string $reference,
        ?string $note,
        ?Membership $actor,
    ): CommissionPayout {
        $entryUuids = array_values(array_unique($entryUuids));

        $payout = DB::transaction(function () use ($beneficiary, $currency, $entryUuids, $paidAt, $method, $reference, $note, $actor) {
            $entries = $this->eligibleQuery()
                ->where('membership_id', $beneficiary->id)
                ->where('currency', $currency)
                ->whereIn('uuid', $entryUuids)
                ->with(['payment:id,uuid,confirmed_at', 'commissionRule' => fn ($q) => $q->withTrashed(), 'sale.opportunity.prospect:id,uuid,business_name'])
                ->lockForUpdate()
                ->get();

            if ($entries->count() !== count($entryUuids)) {
                throw new RuntimeException('Algunas comisiones ya no están disponibles (cambiaron de estado). Recarga la pantalla e inténtalo de nuevo.');
            }

            $now = now();

            foreach ($entries as $entry) {
                if ($this->availableOn($entry)->gt($now)) {
                    throw new RuntimeException('Una de las comisiones elegidas todavía está en periodo de espera.');
                }
            }

            $total = (int) $entries->sum('amount_cents');

            if ($total <= 0) {
                throw new RuntimeException('El total a pagar debe ser mayor que cero. Los descuentos por reembolso se compensan con comisiones futuras.');
            }

            $payout = CommissionPayout::create([
                'membership_id' => $beneficiary->id,
                'currency' => $currency,
                'total_cents' => $total,
                'entries_count' => $entries->count(),
                'paid_at' => $paidAt->toDateString(),
                'method' => $method,
                'reference' => $reference,
                'note' => $note,
                'entries_snapshot' => $entries->map(fn (CommissionLedger $entry) => [
                    'uuid' => $entry->uuid,
                    'type' => $entry->entry_type,
                    'reversal' => $entry->reverses_ledger_id !== null,
                    'amount_cents' => $entry->amount_cents,
                    'client' => $entry->sale?->opportunity?->prospect?->business_name,
                ])->all(),
                'created_by_membership_id' => $actor?->id,
            ]);

            foreach ($entries as $entry) {
                CommissionPayoutEntry::create([
                    'commission_payout_id' => $payout->id,
                    'commission_ledger_id' => $entry->id,
                    'amount_cents' => $entry->amount_cents,
                ]);
            }

            AuditLog::record(
                actor: $actor,
                action: 'commission_payout.created',
                subject: $payout,
                before: [],
                after: ['total_cents' => $total, 'currency' => $currency, 'entries' => $entries->count(), 'paid_at' => $paidAt->toDateString()],
            );

            return $payout;
        });

        $this->notify($beneficiary, 'commission.paid', 'Comisión pagada', sprintf(
            'Se registró un pago de %s %s%s.',
            $payout->currency,
            number_format($payout->total_cents / 100, 2),
            $method ? " ({$method})" : '',
        ), $payout);

        return $payout;
    }

    /** Anula un pago y libera sus comisiones. */
    public function void(CommissionPayout $payout, string $reason, ?Membership $actor): void
    {
        DB::transaction(function () use ($payout, $reason, $actor) {
            $payout = CommissionPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->voided_at !== null) {
                throw new RuntimeException('Este pago ya estaba anulado.');
            }

            CommissionPayoutEntry::where('commission_payout_id', $payout->id)->delete();

            $payout->update([
                'voided_at' => now(),
                'voided_by_membership_id' => $actor?->id,
                'void_reason' => $reason,
            ]);

            AuditLog::record(
                actor: $actor,
                action: 'commission_payout.voided',
                subject: $payout,
                before: ['voided' => false],
                after: ['voided' => true, 'reason' => $reason],
            );
        });

        $this->notify($payout->membership, 'commission.payout_voided', 'Pago de comisión anulado', "Se anuló un pago registrado de {$payout->currency} ".number_format($payout->total_cents / 100, 2).": {$reason}", $payout);
    }

    /**
     * Asientos que todavía no se pagan:
     * - originales sin reversa, o
     * - reversas cuyo original ya se pagó (descuento que se compensa en el siguiente pago).
     * Una pareja original + reversa sin pagar se cancela sola y no aparece.
     */
    private function eligibleQuery(): Builder
    {
        return CommissionLedger::query()
            ->where('amount_cents', '!=', 0)
            ->whereDoesntHave('payoutItem')
            ->where(function (Builder $q) {
                $q->where(fn (Builder $original) => $original->whereNull('reverses_ledger_id')->whereDoesntHave('reversedBy'))
                    ->orWhere(fn (Builder $reversal) => $reversal->whereNotNull('reverses_ledger_id')->whereHas('reverses.payoutItem'));
            });
    }

    /** Desde cuándo se puede pagar: pago confirmado + días de espera de la regla. Los descuentos son inmediatos. */
    public function availableOn(CommissionLedger $entry): CarbonInterface
    {
        if ($entry->reverses_ledger_id !== null) {
            return $entry->created_at;
        }

        $base = $entry->payment?->confirmed_at ?? $entry->created_at;

        return $base->copy()->addDays((int) ($entry->commissionRule?->waiting_period_days ?? 0));
    }

    private function notify(?Membership $beneficiary, string $type, string $title, string $body, CommissionPayout $payout): void
    {
        if (! $beneficiary) {
            return;
        }

        try {
            Notification::create([
                'membership_id' => $beneficiary->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => ['payout_uuid' => $payout->uuid],
            ]);

            app()->terminating(fn () => app(WebPushService::class)->sendToUser($beneficiary->user_id, [
                'title' => $title,
                'body' => $body,
                'url' => '/notifications',
                'tag' => 'payout-'.$payout->uuid,
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}