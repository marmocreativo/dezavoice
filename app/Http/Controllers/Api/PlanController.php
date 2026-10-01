<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Membership;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $marketId = $actor->market_id ?? optional(
            \App\Models\Market::where('uuid', $request->query('market_uuid'))->first()
        )->id;

        abort_unless($marketId, 422, 'No se pudo determinar el mercado.');

        $plans = Plan::with('market')->where('market_id', $marketId)->orderBy('price_cents')->get();

        return PlanResource::collection($plans);
    }
}