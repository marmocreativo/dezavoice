<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\ProspectStatusHistory;
use App\Models\SalesProspect;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProspectStatusService
{
    /**
     * Transiciones que un vendedor puede disparar manualmente.
     * El embudo avanza como máximo hasta "propuesta_enviada" —
     * de ahí en adelante solo el sistema mueve el estado (ver
     * transitionsSystemOnly). Los alternos son alcanzables desde
     * cualquier punto activo del embudo.
     */
    private const TRANSITIONS_MANUAL = [
        'nuevo' => ['calificado', 'no_calificado', 'perdido', 'pausado', 'cancelado'],
        'calificado' => ['demo_programada', 'perdido', 'pausado', 'cancelado'],
        'demo_programada' => ['demo_realizada', 'perdido', 'pausado', 'cancelado'],
        'demo_realizada' => ['propuesta_enviada', 'perdido', 'pausado', 'cancelado'],
        'propuesta_enviada' => ['perdido', 'pausado', 'cancelado'],
        'pausado' => ['calificado', 'demo_programada', 'demo_realizada', 'propuesta_enviada', 'perdido', 'cancelado'],
    ];

    /**
     * Transiciones que solo dispara el sistema, nunca un controlador
     * de API a partir de un request de usuario. Hoy solo confirmPayment()
     * las usa.
     */
    private const TRANSITIONS_SYSTEM = [
        'propuesta_enviada' => ['pago_pendiente', 'venta_ganada'],
        'pago_pendiente' => ['venta_ganada', 'perdido'],
        'venta_ganada' => ['activacion'],
        'activacion' => ['cliente_activo'],
        'cliente_activo' => ['reembolsado'],
    ];

    private const REQUIRES_LOST_REASON = ['perdido'];
    private const REQUIRES_NEXT_ACTION = ['pausado'];

    /**
     * Movimiento manual, disparado por un vendedor o supervisor
     * desde la API. Rechaza cualquier transición fuera del mapa manual,
     * incluido cualquier intento de llegar a venta_ganada por esta vía.
     */
    public function transitionManually(
        SalesProspect $prospect,
        string $toStatus,
        Membership $actor,
        ?string $note = null,
        ?string $lostReason = null,
    ): SalesProspect {
        $allowed = self::TRANSITIONS_MANUAL[$prospect->status] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw new RuntimeException(
                "No se puede mover el prospecto de '{$prospect->status}' a '{$toStatus}' manualmente."
            );
        }

        return $this->applyTransition($prospect, $toStatus, $actor, $note, $lostReason);
    }

    /**
     * Movimiento disparado por el sistema (webhook de pago confirmado,
     * caso de activación completado, reembolso procesado). $actor es
     * null cuando no hay una persona detrás del cambio.
     */
    public function transitionBySystem(
        SalesProspect $prospect,
        string $toStatus,
        ?Membership $actor = null,
        ?string $note = null,
    ): SalesProspect {
        $allowed = self::TRANSITIONS_SYSTEM[$prospect->status] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw new RuntimeException(
                "Transición de sistema inválida: '{$prospect->status}' a '{$toStatus}'."
            );
        }

        return $this->applyTransition($prospect, $toStatus, $actor, $note, null);
    }

    private function applyTransition(
        SalesProspect $prospect,
        string $toStatus,
        ?Membership $actor,
        ?string $note,
        ?string $lostReason,
    ): SalesProspect {
        if (in_array($toStatus, self::REQUIRES_LOST_REASON, true) && empty($lostReason)) {
            throw new RuntimeException("El estado '{$toStatus}' requiere un motivo (lost_reason).");
        }

        return DB::transaction(function () use ($prospect, $toStatus, $actor, $note, $lostReason) {
            $fromStatus = $prospect->status;

            $prospect->status = $toStatus;

            if ($lostReason !== null) {
                $prospect->lost_reason = $lostReason;
            }

            $prospect->save();

            ProspectStatusHistory::create([
                'sales_prospect_id' => $prospect->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by_membership_id' => $actor?->id,
                'note' => $note,
                'changed_at' => now(),
            ]);

            return $prospect->fresh();
        });
    }
}