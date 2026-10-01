<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientSubscriptionResource;
use App\Models\Membership;
use App\Models\Subscription;
use Illuminate\Http\Request;

class ClientSubscriptionController extends Controller
{
    public function show(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        abort_unless($actor->role === 'client', 403, 'Este endpoint es solo para clientes.');
        abort_unless($actor->organization_id, 404, 'Tu membresía no tiene una organización asociada.');

        $subscription = Subscription::where('organization_id', $actor->organization_id)
            ->with(['organization', 'plan'])
            ->latest('started_at')
            ->first();

        abort_unless($subscription, 404, 'No se encontró ninguna suscripción para tu organización.');

        return new ClientSubscriptionResource($subscription);
    }
}