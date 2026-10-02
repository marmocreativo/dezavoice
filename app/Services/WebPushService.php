<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * Envía una notificación a todos los dispositivos de un usuario y devuelve cuántos la aceptaron.
     * Los dispositivos cuya suscripción ya expiró se eliminan solos.
     *
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $payload
     */
    public function sendToUser(int $userId, array $payload): int
    {
        if (! $this->isConfigured()) {
            Log::warning('Web Push: faltan las claves VAPID.');

            return 0;
        }

        $devices = DeviceToken::where('user_id', $userId)->get();

        if ($devices->isEmpty()) {
            return 0;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        foreach ($devices as $device) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $device->endpoint,
                        'publicKey' => $device->p256dh,
                        'authToken' => $device->auth,
                        'contentEncoding' => 'aes128gcm',
                    ]),
                    $body,
                    ['TTL' => 3600, 'urgency' => 'high'],
                );
            } catch (\Throwable $e) {
                // Una suscripción mal formada no debe impedir las demás.
                Log::warning('Web Push: suscripción inválida.', ['device' => $device->uuid, 'error' => $e->getMessage()]);
            }
        }

        $sent = 0;

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                $sent++;
                DeviceToken::where('endpoint', $endpoint)->update(['last_used_at' => now()]);

                continue;
            }

            if ($report->isSubscriptionExpired()) {
                DeviceToken::where('endpoint', $endpoint)->delete();

                continue;
            }

            Log::warning('Web Push: envío fallido.', ['endpoint' => $endpoint, 'reason' => $report->getReason()]);
        }

        return $sent;
    }
}