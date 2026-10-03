<?php

namespace App\Services\Retell;

use App\Models\ClientMessage;
use App\Models\RetellCall;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetellCallService
{
    public function markStarted(RetellCall $call, mixed $startTimestampMs): void
    {
        if ($call->status !== 'created') {
            return;
        }

        $call->update([
            'status' => 'started',
            'started_at' => $startTimestampMs ? Carbon::createFromTimestampMs((int) $startTimestampMs) : now(),
        ]);
    }

    /** Guarda el pedido confirmado. Idempotente por llamada. */
    public function registerOrder(RetellCall $call, string $resumen, ?float $total, ?string $contacto = null): ClientMessage
    {
        $organization = $call->organization;
        $labels = \App\Models\AgentProfile::requestLabelsFor($organization);
        $suffix = $call->canal === 'web_test' ? ' (prueba)' : '';

        $texto = "{$labels['label']}{$suffix}\n".trim($resumen);

        if (filled($contacto)) {
            $texto .= "\nContacto: ".trim($contacto);
        }

        if ($total !== null) {
            $currencyWord = \App\Models\AgentProfile::currencyWords(\App\Models\Market::find($organization?->market_id)?->currency)[0];
            $texto .= "\nTotal: ".number_format($total, 2).' '.$currencyWord;
        }

        return ClientMessage::updateOrCreate(
            ['retell_call_id' => $call->retell_call_id],
            [
                'organization_id' => $call->organization_id,
                'subscription_id' => $call->subscription_id,
                'plan_id' => $call->plan_id,
                'fecha' => $call->started_at ?? now(),
                'canal' => $call->canal,
                'tipo' => 'pedido',
                'mensaje' => $texto,
            ],
        );
    }

    /**
     * Cierra la llamada y descuenta sus minutos de la suscripción. Idempotente:
     * Retell reintenta los webhooks, así que solo se aplica la primera vez.
     */
    public function markEnded(RetellCall $call, array $data): void
    {
        DB::transaction(function () use ($call, $data) {
            $call = RetellCall::whereKey($call->id)->lockForUpdate()->firstOrFail();

            if ($call->minutos_aplicados_at !== null) {
                return;
            }

            $start = isset($data['start_timestamp']) ? (int) $data['start_timestamp'] : null;
            $end = isset($data['end_timestamp']) ? (int) $data['end_timestamp'] : null;

            $durationMs = isset($data['duration_ms'])
                ? (int) $data['duration_ms']
                : (($start && $end) ? max(0, $end - $start) : 0);

            $minutes = round($durationMs / 60000, 2);

            $call->update([
                'status' => 'ended',
                'started_at' => $start ? Carbon::createFromTimestampMs($start) : $call->started_at,
                'ended_at' => $end ? Carbon::createFromTimestampMs($end) : now(),
                'duration_ms' => $durationMs,
                'minutos_consumidos' => $minutes,
                'minutos_aplicados_at' => now(),
                'disconnection_reason' => $data['disconnection_reason'] ?? null,
            ]);

            Subscription::whereKey($call->subscription_id)->increment('minutos_utilizados', $minutes);

            $message = ClientMessage::where('retell_call_id', $call->retell_call_id)->first();

            if ($message) {
                $message->update(['minutos_consumidos' => $minutes]);

                return;
            }

            // Llamada sin pedido: queda registrada igual, con lo conversado.
            $transcript = trim((string) ($data['transcript'] ?? ''));

            ClientMessage::create([
                'organization_id' => $call->organization_id,
                'subscription_id' => $call->subscription_id,
                'plan_id' => $call->plan_id,
                'retell_call_id' => $call->retell_call_id,
                'canal' => $call->canal,
                'tipo' => 'llamada',
                'fecha' => $call->started_at ?? now(),
                'minutos_consumidos' => $minutes,
                'mensaje' => $transcript === ''
                    ? 'Llamada sin pedido confirmado.'
                    : "Llamada sin pedido confirmado.\n\n".Str::limit($transcript, 4000),
            ]);
        });
    }
}