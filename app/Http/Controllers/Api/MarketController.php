<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMarketRequest;
use App\Http\Requests\UpdateMarketRequest;
use App\Http\Resources\MarketResource;
use App\Models\Market;
use App\Models\Membership;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function index(Request $request)
    {
        return MarketResource::collection(Market::orderBy('name')->get());
    }

    public function store(StoreMarketRequest $request)
    {
        $this->authorizeAdmin($request);

        $market = Market::create([
            ...$request->validated(),
            'code' => strtoupper($request->validated('code')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return new MarketResource($market);
    }

    public function update(UpdateMarketRequest $request, Market $market)
    {
        $this->authorizeAdmin($request);

        $data = $request->validated();

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $market->update($data);

        return new MarketResource($market->fresh());
    }

    public function managers(Request $request, Market $market)
    {
        $this->authorizeAdmin($request);

        $wonStatuses = ['venta_ganada', 'activacion', 'cliente_activo'];
        $lostStatuses = ['perdido', 'cancelado', 'reembolsado'];
        $pendingStatuses = ['nuevo', 'calificado', 'demo_programada', 'demo_realizada', 'propuesta_enviada', 'pago_pendiente'];

        $managers = Membership::where('role', 'manager')
            ->where('market_id', $market->id)
            ->where('status', 'active')
            ->with(['user:id,name', 'territory:id,name'])
            ->get()
            ->map(function (Membership $manager) use ($wonStatuses, $lostStatuses, $pendingStatuses) {
                $treeIds = $manager->descendantIds();
                $supervisorIds = Membership::whereIn('id', $treeIds)->where('role', 'supervisor')->pluck('id');
                $sellerIds = Membership::whereIn('id', $treeIds)->where('role', 'seller')->pluck('id');

                $counts = \App\Models\SalesProspect::whereIn('owner_membership_id', $sellerIds)
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $won = $counts->only($wonStatuses)->sum();
                $lost = $counts->only($lostStatuses)->sum();
                $pending = $counts->only($pendingStatuses)->sum();
                $total = $won + $lost + $pending;

                return [
                    'uuid' => $manager->uuid,
                    'name' => $manager->user->name,
                    'territory' => $manager->territory?->name,
                    'supervisors_count' => $supervisorIds->count(),
                    'sellers_count' => $sellerIds->count(),
                    'won_percent' => $total > 0 ? round($won / $total * 100, 1) : 0,
                ];
            });

        return response()->json(['data' => $managers]);
    }

    private function authorizeAdmin(Request $request): void
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        abort_unless($actor->role === 'deza_admin', 403, 'Solo un administrador puede gestionar mercados.');
    }
}