<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTerritoryRequest;
use App\Http\Requests\UpdateTerritoryRequest;
use App\Http\Resources\TerritoryResource;
use App\Models\Market;
use App\Models\Membership;
use App\Models\Territory;
use Illuminate\Http\Request;

class TerritoryController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $marketId = $this->resolveMarketId($request, $actor);

        $wonStatuses = ['venta_ganada', 'activacion', 'cliente_activo'];
        $lostStatuses = ['perdido', 'cancelado', 'reembolsado'];
        $pendingStatuses = ['nuevo', 'calificado', 'demo_programada', 'demo_realizada', 'propuesta_enviada', 'pago_pendiente'];

        $territories = Territory::where('market_id', $marketId)
            ->with(['market', 'assignedManager.user'])
            ->withCount(['memberships as active_sellers_count' => fn ($q) => $q->where('role', 'seller')->where('status', 'active')])
            ->withCount(['memberships as prospects_won_count' => fn ($q) => $q
                ->join('sales_prospects', 'sales_prospects.owner_membership_id', '=', 'memberships.id')
                ->whereIn('sales_prospects.status', $wonStatuses)
            ])
            ->withCount(['memberships as prospects_lost_count' => fn ($q) => $q
                ->join('sales_prospects', 'sales_prospects.owner_membership_id', '=', 'memberships.id')
                ->whereIn('sales_prospects.status', $lostStatuses)
            ])
            ->withCount(['memberships as prospects_pending_count' => fn ($q) => $q
                ->join('sales_prospects', 'sales_prospects.owner_membership_id', '=', 'memberships.id')
                ->whereIn('sales_prospects.status', $pendingStatuses)
            ])
            ->get();

        return TerritoryResource::collection($territories);
    }

    private function resolveMarketId(Request $request, Membership $actor): int
    {
        $marketUuid = $request->query('market_uuid');

        if ($marketUuid) {
            $market = Market::where('uuid', $marketUuid)->firstOrFail();

            if ($actor->role !== 'deza_admin' && $actor->market_id !== $market->id) {
                abort(403, 'No tienes acceso a este mercado.');
            }

            return $market->id;
        }

        abort_unless($actor->market_id, 422, 'Debes indicar market_uuid en la petición.');

        return $actor->market_id;
    }

    public function store(StoreTerritoryRequest $request)
    {
        $this->authorizeAdmin($request);

        $market = Market::where('uuid', $request->validated('market_uuid'))->firstOrFail();

        $territory = Territory::firstOrCreate([
            'market_id' => $market->id,
            'name' => $request->validated('name'),
        ]);

        return new TerritoryResource($territory->load(['market', 'assignedManager.user']));
    }

    public function update(UpdateTerritoryRequest $request, Territory $territory)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $isOwnerManager = $actor->role === 'manager' && $actor->market_id === $territory->market_id;

        abort_unless($actor->role === 'deza_admin' || $isOwnerManager, 403, 'No tienes permiso para editar este territorio.');

        $territory->update($request->validated());

        return new TerritoryResource($territory->fresh()->load(['market', 'assignedManager.user']));
    }

    public function destroy(Request $request, Territory $territory)
    {
        $this->authorizeAdmin($request);

        if ($territory->assigned_manager_membership_id) {
            abort(422, 'No se puede eliminar un territorio con gerente asignado.');
        }

        if ($territory->memberships()->exists()) {
            abort(422, 'No se puede eliminar un territorio con vendedores o supervisores asignados.');
        }

        $territory->delete();

        return response()->noContent();
    }

    public function assignManager(Request $request, Territory $territory)
    {
        $this->authorizeAdmin($request);

        $managerUuid = $request->validate(['manager_membership_uuid' => ['required', 'exists:memberships,uuid']])['manager_membership_uuid'];

        $manager = Membership::where('uuid', $managerUuid)->where('role', 'manager')->firstOrFail();

        $manager->update(['territory_id' => $territory->id]);
        $territory->update(['assigned_manager_membership_id' => $manager->id]);

        return new TerritoryResource($territory->fresh()->load(['market', 'assignedManager.user']));
    }

    public function supervisors(Request $request, Territory $territory)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $isOwnerManager = $actor->role === 'manager' && $actor->market_id === $territory->market_id;

        abort_unless($actor->role === 'deza_admin' || $isOwnerManager, 403, 'No tienes acceso a este territorio.');

        $wonStatuses = ['venta_ganada', 'activacion', 'cliente_activo'];
        $lostStatuses = ['perdido', 'cancelado', 'reembolsado'];
        $pendingStatuses = ['nuevo', 'calificado', 'demo_programada', 'demo_realizada', 'propuesta_enviada', 'pago_pendiente'];

        $supervisors = Membership::where('role', 'supervisor')
            ->where('territory_id', $territory->id)
            ->where('status', 'active')
            ->with('user:id,name')
            ->get()
            ->map(function (Membership $supervisor) use ($wonStatuses, $lostStatuses, $pendingStatuses) {
                $sellerIds = $supervisor->descendantIds();

                $counts = \App\Models\SalesProspect::whereIn('owner_membership_id', $sellerIds)
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $won = $counts->only($wonStatuses)->sum();
                $lost = $counts->only($lostStatuses)->sum();
                $pending = $counts->only($pendingStatuses)->sum();
                $total = $won + $lost + $pending;

                return [
                    'uuid' => $supervisor->uuid,
                    'name' => $supervisor->user->name,
                    'codigo' => $supervisor->codigo,
                    'sellers_count' => Membership::whereIn('id', $sellerIds)->where('role', 'seller')->count(),
                    'prospects_total' => $total,
                    'prospects_won_count' => $won,
                    'prospects_lost_count' => $lost,
                    'prospects_pending_count' => $pending,
                    'prospects_won_percent' => $total > 0 ? round($won / $total * 100, 1) : 0,
                    'prospects_lost_percent' => $total > 0 ? round($lost / $total * 100, 1) : 0,
                    'prospects_pending_percent' => $total > 0 ? round($pending / $total * 100, 1) : 0,
                ];
            });

        return response()->json(['data' => $supervisors]);
    }

    private function authorizeAdmin(Request $request): void
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        abort_unless($actor->role === 'deza_admin', 403, 'Solo un administrador puede gestionar territorios.');
    }
}