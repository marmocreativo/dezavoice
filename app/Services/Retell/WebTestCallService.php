<?php

namespace App\Services\Retell;

use App\Exceptions\WebTestException;
use App\Models\AgentProfile;
use App\Models\Organization;
use App\Models\RetellCall;
use App\Models\Subscription;

class WebTestCallService
{
    public function __construct(private readonly RetellApi $retell) {}

    public function activeSubscription(Organization $organization): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
    }

    public function remainingMinutes(Subscription $subscription): float
    {
        return (float) $subscription->minutos_mensuales - (float) $subscription->minutos_utilizados;
    }

    /**
     * Valida los minutos y crea la llamada en Retell.
     *
     * @return array<string, mixed> lo que necesita el navegador para conectarse
     */
    public function start(Organization $organization): array
    {
        $agentId = (string) config('services.retell.web_test_agent_id');

        $missing = array_keys(array_filter([
            'RETELL_API_KEY' => ! config('services.retell.api_key'),
            'RETELL_WEB_TEST_AGENT_ID' => $agentId === '',
        ]));

        if ($missing !== []) {
            \Illuminate\Support\Facades\Log::error('web_test: falta configuración de Retell.', ['faltan' => $missing]);

            throw new WebTestException('La prueba de voz no está configurada todavía.', 503);
        }

        $subscription = $this->activeSubscription($organization);

        if (! $subscription) {
            throw new WebTestException('Este cliente no tiene una suscripción activa.', 403);
        }

        $remaining = $this->remainingMinutes($subscription);

        // Retell no acepta llamadas de menos de 1 minuto de duración máxima.
        if ($remaining < 1) {
            throw new WebTestException('No quedan minutos disponibles en el plan de este cliente.', 403);
        }

        $testMinutes = max(1, (int) config('services.retell.web_test_max_minutes', 5));

        $busy = RetellCall::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['created', 'started'])
            ->where('created_at', '>=', now()->subMinutes($testMinutes + 2))
            ->exists();

        if ($busy) {
            throw new WebTestException('Ya hay una llamada de prueba en curso para este cliente.', 409);
        }

        // La llamada se corta sola cuando se acabarían los minutos (o al tope de prueba).
        $maxDurationMs = (int) max(60000, floor(min($remaining, $testMinutes, 120) * 60000));

        $payload = [
            'agent_id' => $agentId,
            'metadata' => [
                'canal' => 'web_test',
                'organization_uuid' => $organization->uuid,
                'subscription_uuid' => $subscription->uuid,
            ],
            'retell_llm_dynamic_variables' => array_merge(
                AgentProfile::variablesFor($organization),
                ['minutos_restantes' => (string) round($remaining, 2)],
            ),
            'agent_override' => [
                'agent' => [
                    'max_call_duration_ms' => $maxDurationMs,
                    // Los eventos de esta llamada llegan a esta URL, sin tocar la configuración del agente.
                    'webhook_url' => rtrim((string) config('app.url'), '/').'/api/v1/webhooks/retell',
                    'webhook_events' => ['call_started', 'call_ended'],
                ],
            ],
        ];

        if ($version = config('services.retell.web_test_agent_version')) {
            $payload['agent_version'] = ctype_digit((string) $version) ? (int) $version : (string) $version;
        }

        $data = $this->retell->createWebCall($payload);

        $call = RetellCall::create([
            'retell_call_id' => $data['call_id'],
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'canal' => 'web_test',
            'status' => 'created',
        ]);

        return [
            'call_uuid' => $call->uuid,
            'call_id' => $data['call_id'],
            'access_token' => $data['access_token'],
            'transport' => $data['transport'],
            'ice_servers' => $data['ice_servers'],
            'expires_at' => $data['expires_at'],
            'max_minutes' => round($maxDurationMs / 60000, 2),
            'remaining_minutes' => round($remaining, 2),
        ];
    }
}