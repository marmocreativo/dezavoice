<?php

namespace App\Services;

use App\Models\ClientMessage;
use App\Models\Membership;
use App\Models\Notification;
use Illuminate\Support\Str;

class OrderNotificationService
{
    public function __construct(private readonly WebPushService $push) {}

    /** Avisa a los usuarios cliente de la organización que llegó un pedido nuevo. Nunca lanza excepciones. */
    public function notifyNewOrder(ClientMessage $message): void
    {
        try {
            $title = \App\Models\AgentProfile::requestLabelsFor($message->organization)['new'];
            $summary = trim((string) preg_replace('/\s+/', ' ', Str::after($message->mensaje, "\n")));
            $body = Str::limit($summary !== '' ? $summary : 'Revisa los detalles en la app.', 140);

            $memberships = Membership::active()
                ->where('role', 'client')
                ->where('organization_id', $message->organization_id)
                ->get();

            foreach ($memberships as $membership) {
                Notification::create([
                    'membership_id' => $membership->id,
                    'type' => 'order.created',
                    'title' => $title,
                    'body' => $body,
                    'data' => ['message_uuid' => $message->uuid],
                ]);

                $this->push->sendToUser($membership->user_id, [
                    'title' => $title,
                    'body' => $body,
                    'url' => '/client/messages',
                    'tag' => 'order-'.$message->uuid,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}