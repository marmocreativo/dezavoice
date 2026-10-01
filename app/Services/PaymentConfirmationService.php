<?php

namespace App\Services;

use App\Models\CommissionLedger;
use App\Models\CommissionPlan;
use App\Models\CommissionRule;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentConfirmationService
{
    public function __construct(
        private readonly ProspectStatusService $statusService,
    ) {}

    /**
     * Procesa un pago confirmado por un proveedor externo. Idempotente:
     * si ya existe un pago con el mismo provider + idempotency_key,
     * devuelve el existente sin duplicar nada.
     */
    public function confirm(
        Opportunity $opportunity,
        string $provider,
        string $externalId,
        string $idempotencyKey,
        int $amountCents,
        string $currency,
        ?array $rawPayload = null,
    ): Payment {
        $existing = Payment::where('provider', $provider)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use (
            $opportunity, $provider, $externalId, $idempotencyKey,
            $amountCents, $currency, $rawPayload,
        ) {
            $payment = Payment::create([
                'opportunity_id' => $opportunity->id,
                'provider' => $provider,
                'external_id' => $externalId,
                'idempotency_key' => $idempotencyKey,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'raw_payload' => $rawPayload,
            ]);

            $prospect = $opportunity->prospect;
            $seller = $prospect->owner;

            if ($prospect->status !== 'venta_ganada') {
                $intermediateStatus = $prospect->status === 'propuesta_enviada'
                    ? 'pago_pendiente'
                    : $prospect->status;

                if ($intermediateStatus !== $prospect->status) {
                    $prospect = $this->statusService->transitionBySystem(
                        prospect: $prospect,
                        toStatus: $intermediateStatus,
                        note: "Pago recibido de {$provider}, en verificación.",
                    );
                }

                $prospect = $this->statusService->transitionBySystem(
                    prospect: $prospect,
                    toStatus: 'venta_ganada',
                    note: "Pago confirmado ({$provider} / {$externalId}).",
                );
            }

            $sale = $seller->sales()->create([
                'opportunity_id' => $opportunity->id,
                'payment_id' => $payment->id,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'validated_at' => now(),
                'status' => 'venta_ganada',
            ]);

            $this->createActivationLedgerEntries($sale, $seller, $opportunity, $payment);

            Notification::create([
                'membership_id' => $seller->id,
                'type' => 'sale.confirmed',
                'title' => 'Venta confirmada',
                'body' => "El pago de {$prospect->business_name} fue confirmado.",
                'data' => ['sale_uuid' => $sale->uuid, 'prospect_uuid' => $prospect->uuid],
            ]);

            return $payment;
        });
    }

    /**
     * Confirma un pago proveniente de una factura de Stripe Subscription.
     * La primera factura (isFirstInvoice) dispara todo el flujo de venta
     * ganada + comisiones de activación para toda la cadena (vendedor,
     * supervisor, gerente). Las facturas siguientes solo registran el
     * Payment y generan comisiones recurrentes para quien siga dentro
     * de su ventana de meses.
     */
    public function confirmInvoice(
        \App\Models\Subscription $subscription,
        string $externalId,
        string $idempotencyKey,
        int $amountCents,
        string $currency,
        bool $isFirstInvoice,
        ?array $rawPayload = null,
    ): Payment {
        $existing = Payment::where('provider', 'stripe')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $opportunity = $subscription->opportunity;

        return DB::transaction(function () use (
            $subscription, $opportunity, $externalId, $idempotencyKey,
            $amountCents, $currency, $isFirstInvoice, $rawPayload,
        ) {
            $payment = Payment::create([
                'opportunity_id' => $opportunity->id,
                'provider' => 'stripe',
                'external_id' => $externalId,
                'idempotency_key' => $idempotencyKey,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'raw_payload' => $rawPayload,
            ]);

            // Cada factura pagada abre un periodo nuevo: los minutos usados vuelven a cero.
            $subscription->update(['status' => 'active', 'minutos_utilizados' => 0]);

            if ($isFirstInvoice) {
                $this->confirmFirstPayment($opportunity, $payment, $amountCents, $currency);
            } else {
                $this->createRecurringLedgerEntries($opportunity, $payment);
            }

            return $payment;
        });
    }

    private function confirmFirstPayment(Opportunity $opportunity, Payment $payment, int $amountCents, string $currency): void
    {
        $prospect = $opportunity->prospect;
        $seller = $prospect->owner;

        if ($prospect->status !== 'venta_ganada') {
            $intermediateStatus = $prospect->status === 'propuesta_enviada' ? 'pago_pendiente' : $prospect->status;

            if ($intermediateStatus !== $prospect->status) {
                $prospect = $this->statusService->transitionBySystem(
                    prospect: $prospect,
                    toStatus: $intermediateStatus,
                    note: 'Pago recibido de stripe, en verificación.',
                );
            }

            $prospect = $this->statusService->transitionBySystem(
                prospect: $prospect,
                toStatus: 'venta_ganada',
                note: "Pago confirmado (stripe / {$payment->external_id}).",
            );
        }

        $sale = $seller->sales()->create([
            'opportunity_id' => $opportunity->id,
            'payment_id' => $payment->id,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'validated_at' => now(),
            'status' => 'venta_ganada',
        ]);

        $this->createActivationLedgerEntries($sale, $seller, $opportunity, $payment);

        Notification::create([
            'membership_id' => $seller->id,
            'type' => 'sale.confirmed',
            'title' => 'Venta confirmada',
            'body' => "El pago de {$prospect->business_name} fue confirmado.",
            'data' => ['sale_uuid' => $sale->uuid, 'prospect_uuid' => $prospect->uuid],
        ]);
    }

    /**
     * Genera la comisión de activación para toda la cadena (vendedor,
     * su supervisor, el gerente de este, etc.), una por cada eslabón
     * que tenga una commission_rule vigente para el plan vendido.
     */
    private function createActivationLedgerEntries(Sale $sale, Membership $seller, Opportunity $opportunity, Payment $payment): void
    {
        foreach ($seller->ancestorChain() as $membership) {
            $rule = $this->resolveApplicableRule($membership, $opportunity->plan_id);

            if (! $rule) {
                continue;
            }

            CommissionLedger::create([
                'membership_id' => $membership->id,
                'sale_id' => $sale->id,
                'payment_id' => $payment->id,
                'commission_rule_id' => $rule->id,
                'entry_type' => 'activation',
                'status' => 'pending',
                'amount_cents' => $rule->activation_amount_cents,
                'currency' => $sale->currency,
                'calculation_snapshot' => [
                    'commission_plan_id' => $rule->commission_plan_id,
                    'commission_plan_version' => $rule->commissionPlan->version,
                    'rule_id' => $rule->id,
                    'role' => $membership->role,
                    'activation_amount_cents' => $rule->activation_amount_cents,
                    'sale_amount_cents' => $sale->amount_cents,
                    'resolved_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }

    /**
     * Genera la comisión recurrente (% sobre el pago) para cada eslabón
     * de la cadena que todavía esté dentro de su ventana de meses.
     * El número de secuencia del pago recurrente (1, 2, 3...) se cuenta
     * sobre los pagos confirmados de esta Opportunity, sin contar la
     * activación (el primer pago).
     */
    private function createRecurringLedgerEntries(Opportunity $opportunity, Payment $payment): void
    {
        $seller = $opportunity->prospect->owner;
        $sale = Sale::where('opportunity_id', $opportunity->id)->latest()->first();

        if (! $sale) {
            return;
        }

        $recurringPaymentNumber = Payment::where('opportunity_id', $opportunity->id)
            ->where('status', 'confirmed')
            ->orderBy('confirmed_at')
            ->pluck('id')
            ->search($payment->id);

        if ($recurringPaymentNumber === false) {
            return;
        }

        // El primer pago confirmado (índice 0) es la activación, no cuenta
        // como mes recurrente. El segundo pago (índice 1) es "mes 1".
        $monthNumber = $recurringPaymentNumber;

        if ($monthNumber < 1) {
            return;
        }

        foreach ($seller->ancestorChain() as $membership) {
            $rule = $this->resolveApplicableRule($membership, $opportunity->plan_id);

            if (! $rule || ! $rule->recurring_percentage || ! $rule->duration_months) {
                continue;
            }

            if ($monthNumber > $rule->duration_months) {
                continue;
            }

            $amountCents = (int) round($payment->amount_cents * ((float) $rule->recurring_percentage / 100));

            CommissionLedger::create([
                'membership_id' => $membership->id,
                'sale_id' => $sale->id,
                'payment_id' => $payment->id,
                'commission_rule_id' => $rule->id,
                'entry_type' => 'recurring',
                'status' => 'pending',
                'amount_cents' => $amountCents,
                'currency' => $payment->currency,
                'calculation_snapshot' => [
                    'commission_plan_id' => $rule->commission_plan_id,
                    'commission_plan_version' => $rule->commissionPlan->version,
                    'rule_id' => $rule->id,
                    'role' => $membership->role,
                    'recurring_percentage' => $rule->recurring_percentage,
                    'month_number' => $monthNumber,
                    'duration_months' => $rule->duration_months,
                    'payment_amount_cents' => $payment->amount_cents,
                    'resolved_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }

    /**
     * Encuentra la regla de comisión vigente para el rol de la membresía
     * y el plan de suscripción vendido, en la fecha actual.
     */
    private function resolveApplicableRule(Membership $membership, ?int $subscriptionPlanId): ?CommissionRule
    {
        if (! $subscriptionPlanId) {
            return null;
        }

        $commissionPlan = CommissionPlan::query()
            ->where('market_id', $membership->market_id)
            ->where('role', $membership->role)
            ->effectiveOn()
            ->orderByDesc('version')
            ->first();

        if (! $commissionPlan) {
            return null;
        }

        return $commissionPlan->rules()->where('plan_id', $subscriptionPlanId)->first();
    }
}