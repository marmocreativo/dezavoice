<?php

namespace App\Services;

use App\Models\ClientMessage;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ClientMessageService
{
    /**
     * Registra un mensaje del cliente y descuenta los minutos de su suscripción.
     * Es el punto de entrada para quien reciba los mensajes (endpoint, n8n, etc.).
     */
    public function record(Subscription $subscription, string $mensaje, float|int|string $minutos, ?CarbonInterface $fecha = null): ClientMessage
    {
        return DB::transaction(function () use ($subscription, $mensaje, $minutos, $fecha) {
            $message = ClientMessage::create([
                'organization_id' => $subscription->organization_id,
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'fecha' => $fecha ?? now(),
                'minutos_consumidos' => $minutos,
                'mensaje' => $mensaje,
            ]);

            // Incremento atómico: no pisa lecturas concurrentes.
            Subscription::whereKey($subscription->id)->increment('minutos_utilizados', (float) $minutos);

            return $message;
        });
    }
}