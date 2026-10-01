<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesTeamRequest;
use App\Http\Resources\SalesTeamResource;
use App\Models\Membership;
use App\Models\SalesTeam;
use Illuminate\Http\Request;

class SalesTeamController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $query = SalesTeam::query()->with(['supervisor.user', 'territory']);

        if ($actor->role === 'manager') {
            $query->where('manager_membership_id', $actor->id);
        } elseif ($actor->role === 'deza_admin') {
            $marketUuid = $request->query('market_uuid');
            abort_unless($marketUuid, 422, 'Debes indicar market_uuid en la petición.');
            $query->whereHas('market', fn ($q) => $q->where('uuid', $marketUuid));
        } else {
            $query->where('id', $actor->sales_team_id);
        }

        $territoryUuid = $request->query('territory_uuid');
        if ($territoryUuid) {
            $query->whereHas('territory', fn ($q) => $q->where('uuid', $territoryUuid));
        }

        $teams = $query->withCount(['memberships as sellers_count' => fn ($q) => $q->where('role', 'seller')])->get();

        return SalesTeamResource::collection($teams);
    }

    public function store(StoreSalesTeamRequest $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        abort_unless($actor->role === 'manager', 403, 'Solo un gerente puede crear equipos de venta.');
        abort_unless($actor->market_id, 422, 'Tu membresía no tiene un mercado asignado.');

        $territoryUuid = $request->validated('territory_uuid');
        $territoryId = $territoryUuid ? \App\Models\Territory::where('uuid', $territoryUuid)->firstOrFail()->id : null;

        $team = SalesTeam::create([
            'market_id' => $actor->market_id,
            'territory_id' => $territoryId,
            'manager_membership_id' => $actor->id,
            'name' => $request->validated('name'),
            'is_active' => true,
        ]);

        return new SalesTeamResource($team->fresh()->load('territory'));
    }

    public function sellers(Request $request, SalesTeam $team)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        abort_unless(
            $actor->role === 'deza_admin' || ($actor->role === 'manager' && $team->manager_membership_id === $actor->id),
            403,
            'No tienes acceso a este equipo.'
        );

        $wonStatuses = ['venta_ganada', 'activacion', 'cliente_activo'];
        $lostStatuses = ['perdido', 'cancelado', 'reembolsado'];
        $pendingStatuses = ['nuevo', 'calificado', 'demo_programada', 'demo_realizada', 'propuesta_enviada', 'pago_pendiente'];

        $sellers = Membership::where('role', 'seller')
            ->where('sales_team_id', $team->id)
            ->where('status', 'active')
            ->with('user:id,name')
            ->get()
            ->map(function (Membership $seller) use ($wonStatuses, $lostStatuses, $pendingStatuses) {
                $counts = \App\Models\SalesProspect::where('owner_membership_id', $seller->id)
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $won = $counts->only($wonStatuses)->sum();
                $lost = $counts->only($lostStatuses)->sum();
                $pending = $counts->only($pendingStatuses)->sum();
                $total = $won + $lost + $pending;

                return [
                    'uuid' => $seller->uuid,
                    'name' => $seller->user->name,
                    'codigo' => $seller->codigo,
                    'prospects_total' => $total,
                    'won_percent' => $total > 0 ? round($won / $total * 100, 1) : 0,
                ];
            });

        return response()->json(['data' => $sellers]);
    }

    public function update(\App\Http\Requests\UpdateSalesTeamRequest $request, SalesTeam $team)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        abort_unless($actor->role === 'manager' && $team->manager_membership_id === $actor->id, 403, 'Solo el gerente dueño de este equipo puede editarlo.');

        $data = $request->validated();

        if (array_key_exists('territory_uuid', $data)) {
            $territory = $data['territory_uuid']
                ? \App\Models\Territory::where('uuid', $data['territory_uuid'])->firstOrFail()
                : null;
            $data['territory_id'] = $territory?->id;
            unset($data['territory_uuid']);
        }

        $team->update($data);

        return new SalesTeamResource($team->fresh()->load(['supervisor.user', 'territory']));
    }

    public function assignSupervisor(Request $request, SalesTeam $team)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        abort_unless($actor->role === 'manager' && $team->manager_membership_id === $actor->id, 403, 'Solo el gerente dueño de este equipo puede asignar su supervisor.');

        $supervisorUuid = $request->validate(['supervisor_membership_uuid' => ['required', 'exists:memberships,uuid']])['supervisor_membership_uuid'];

        $supervisor = Membership::where('uuid', $supervisorUuid)->where('role', 'supervisor')->firstOrFail();

        $supervisor->update(['sales_team_id' => $team->id]);

        return new SalesTeamResource($team->fresh()->load('supervisor.user'));
    }
}