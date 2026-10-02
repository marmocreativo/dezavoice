<?php

namespace App\Services\Retell;

use App\Models\AgentProfile;
use App\Models\ClientPhoneNumber;
use App\Models\RetellCall;
use Illuminate\Support\Facades\Log;

class InboundCallService
{
    public function __construct(private readonly WebTestCallService $calls) {}

    /**
     * Decide qué hacer con una llamada entrante: rechazarla o atenderla con el agente y los datos del cliente.
     *
     * @return array<string, mixed> contenido del objeto `call_inbound` de la respuesta
     */
    public function handle(?string $callId, ?string $from, ?string $to): array
    {
        $number = $to ? ClientPhoneNumber::with('organization')->where('phone_number', $to)->first() : null;

        if (! $number || ! $number->is_active || ! $number->organization) {
            return $this->reject('número no registrado o inactivo', $callId, $to);
        }

        if (! $callId) {
            // Sin call_id no podríamos ligar el pedido ni descontar minutos.
            return $this->reject('el aviso no trae call_id', $callId, $to);
        }

        $organization = $number->organization;
        $subscription = $this->calls->activeSubscription($organization);

        if (! $subscription) {
            return $this->reject('el cliente no tiene suscripción activa', $callId, $to);
        }

        $remaining = $this->calls->remainingMinutes($subscription);

        // Retell no admite una duración máxima menor a 1 minuto.
        if ($remaining < 1) {
            return $this->reject('el cliente no tiene minutos disponibles', $callId, $to);
        }

        $agentId = $number->retell_agent_id
            ?: config('services.retell.phone_agent_id')
            ?: config('services.retell.web_test_agent_id');

        if (blank($agentId)) {
            Log::error('Llamada entrante: no hay agente configurado.', ['to' => $to]);

            return ['reject' => true];
        }

        $maxMinutes = max(1, (int) config('services.retell.phone_max_minutes', 10));
        $maxDurationMs = (int) max(60000, floor(min($remaining, $maxMinutes, 120) * 60000));

        // Retell reintenta con el mismo call_id: la llamada se registra una sola vez.
        RetellCall::firstOrCreate(
            ['retell_call_id' => $callId],
            [
                'organization_id' => $organization->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'canal' => 'phone',
                'status' => 'created',
                'from_number' => $from,
                'to_number' => $to,
            ],
        );

        $response = [
            'override_agent_id' => $agentId,
            'dynamic_variables' => array_merge(
                AgentProfile::variablesFor($organization),
                ['minutos_restantes' => (string) round($remaining, 2)],
            ),
            'metadata' => [
                'canal' => 'phone',
                'organization_uuid' => $organization->uuid,
                'subscription_uuid' => $subscription->uuid,
                'phone_number' => $to,
            ],
            'agent_override' => [
                'agent' => [
                    'max_call_duration_ms' => $maxDurationMs,
                    // Los eventos de esta llamada llegan a este servidor, sin tocar la configuración del agente.
                    'webhook_url' => rtrim((string) config('app.url'), '/').'/api/v1/webhooks/retell',
                ],
            ],
        ];

        $version = config('services.retell.phone_agent_version');

        if (filled($version) && ctype_digit((string) $version)) {
            $response['override_agent_version'] = (int) $version;
        }

        Log::info('Llamada entrante aceptada.', [
            'call_id' => $callId,
            'organization' => $organization->uuid,
            'to' => $to,
            'remaining_minutes' => round($remaining, 2),
        ]);

        return $response;
    }

    /** Solo `reject: true`: Retell recomienda no mezclar otros campos al rechazar. */
    private function reject(string $reason, ?string $callId, ?string $to): array
    {
        Log::warning('Llamada entrante rechazada: '.$reason.'.', ['call_id' => $callId, 'to' => $to]);

        return ['reject' => true];
    }
}