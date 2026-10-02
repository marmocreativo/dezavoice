<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientMessageResource;
use App\Models\ClientMessage;
use App\Models\Membership;
use App\Models\Subscription;
use Illuminate\Http\Request;

class ClientMessageController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        abort_unless($actor->role === 'client', 403, 'Este endpoint es solo para clientes.');
        abort_unless($actor->organization_id, 404, 'Tu membresía no tiene una organización asociada.');

        $messages = ClientMessage::query()
            ->where('organization_id', $actor->organization_id)
            ->with('plan:id,name')
            ->when(
                in_array($request->query('tipo'), ['pedido', 'llamada'], true),
                fn ($q) => $q->where('tipo', $request->query('tipo')),
            )
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->cursorPaginate(20);

        $subscription = Subscription::where('organization_id', $actor->organization_id)
            ->latest('started_at')
            ->first();

        $usage = null;

        if ($subscription) {
            $limit = (int) $subscription->minutos_mensuales;
            $used = (float) $subscription->minutos_utilizados;

            $usage = [
                'minutos_mensuales' => $limit,
                'minutos_utilizados' => $used,
                'minutos_restantes' => max(0, round($limit - $used, 2)),
                'periodo_hasta' => $subscription->current_period_end?->toIso8601String(),
            ];
        }

        return ClientMessageResource::collection($messages)->additional(['usage' => $usage]);
    }
}