<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CommissionLedger;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\Payment;
use App\Services\ProspectStatusService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommissionReverser
{
    public function __construct(
        private readonly ProspectStatusService $statusService,
    ) {}

    /**
     * Revierte todos los asientos de comisión ligados a un pago,
     * y si el prospecto asociado admite la transición, lo mueve a
     * "reembolsado". Rechaza la reversión si ya pasó la ventana
     * de reversión definida en la regla de comisión.
     */
    public function reverse(Payment $payment, ?string $reason = null, ?Membership $actor = null): array
    {
        $sale = $payment->sale;

        if (! $sale) {
            throw new RuntimeException('El pago no tiene una venta asociada; nada que revertir.');
        }

        $entries = CommissionLedger::where('payment_id', $payment->id)
            ->whereNull('reverses_ledger_id')
            ->whereDoesntHave('reversedBy')
            ->get();

        if ($entries->isEmpty()) {
            throw new RuntimeException('No hay asientos de comisión activos para este pago.');
        }

        foreach ($entries as $entry) {
            $this->assertWithinReversalWindow($entry, $payment);
        }

        return DB::transaction(function () use ($entries, $payment, $sale, $reason, $actor) {
            $reversals = $entries->map(fn (CommissionLedger $entry) => $this->createReversal($entry, $reason));

            $payment->update(['status' => 'refunded']);
            $sale->update(['status' => 'reembolsado']);

            $this->transitionProspectIfPossible($sale, $reason);

            AuditLog::record(
                actor: $actor,
                action: 'commission.reversed',
                subject: $payment,
                before: ['status' => 'confirmed'],
                after: ['status' => 'refunded', 'reason' => $reason, 'entries_reversed' => $reversals->count()],
            );

            $totalReversedCents = $reversals->sum(fn (CommissionLedger $r) => abs($r->amount_cents));

            Notification::create([
                'membership_id' => $sale->seller_membership_id,
                'type' => 'commission.reversed',
                'title' => 'Comisión revertida',
                'body' => "Se revirtió una comisión de {$sale->currency} " . number_format($totalReversedCents / 100, 2) . ($reason ? ": {$reason}" : '.'),
                'data' => ['payment_uuid' => $payment->uuid, 'amount_cents' => $totalReversedCents],
            ]);

            return $reversals->all();
        });
    }

    private function assertWithinReversalWindow(CommissionLedger $entry, Payment $payment): void
    {
        $windowDays = $entry->commissionRule->reversal_window_days;

        if ($windowDays === null || $windowDays === 0) {
            return;
        }

        $deadline = $payment->confirmed_at?->copy()->addDays($windowDays);

        if ($deadline && now()->greaterThan($deadline)) {
            throw new RuntimeException(
                "La ventana de reversión de {$windowDays} días para este pago ya venció ({$deadline->toDateString()})."
            );
        }
    }

    private function createReversal(CommissionLedger $entry, ?string $reason): CommissionLedger
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
            ],
            'reverses_ledger_id' => $entry->id,
            'note' => $reason,
        ]);
    }

    private function transitionProspectIfPossible($sale, ?string $reason): void
    {
        $prospect = $sale->opportunity->prospect;

        try {
            $this->statusService->transitionBySystem(
                prospect: $prospect,
                toStatus: 'reembolsado',
                note: $reason ?? 'Pago reembolsado.',
            );
        } catch (RuntimeException) {
            // El prospecto no está en un estado desde el que "reembolsado"
            // sea una transición válida (p. ej. sigue en venta_ganada sin
            // haber llegado a cliente_activo). La reversión del dinero ya
            // ocurrió; el estado del prospecto se deja como está en vez
            // de forzar una transición inválida.
        }
    }
}