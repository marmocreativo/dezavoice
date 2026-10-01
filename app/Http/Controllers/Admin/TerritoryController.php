<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TerritoryRequest;
use App\Models\Market;
use App\Models\Membership;
use App\Models\Territory;
use App\Models\User;
use App\Services\PersonInvitationService;
use App\Services\TerritorySupervisorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TerritoryController extends Controller
{
    public function __construct(
        private readonly TerritorySupervisorService $supervisors,
        private readonly PersonInvitationService $invitations,
    ) {}

    public function create(Market $market): View
    {
        return view('admin.territories.create', [
            'market' => $market,
            'territory' => new Territory(),
            'candidates' => $this->supervisorCandidates(),
        ]);
    }

    public function store(TerritoryRequest $request, Market $market): RedirectResponse
    {
        $data = $request->validated();

        [$territory, $assignment] = DB::transaction(function () use ($market, $data) {
            $territory = Territory::create([
                'market_id' => $market->id,
                'name' => $data['name'],
                'geo_reference' => $data['geo_reference'] ?? null,
                // Columna heredada que la API y la PWA todavía leen.
                'assigned_manager_membership_id' => $market->managers()->value('id'),
            ]);

            return [$territory, $this->supervisors->assign($territory, $data)];
        });

        return redirect()
            ->route('admin.territories.show', $territory->uuid)
            ->with('status', $this->statusMessage('Territorio creado.', $assignment))
            ->with('warning', $this->notifySupervisor($assignment));
    }

    public function show(Territory $territory): View
    {
        $territory->load(['market', 'supervisors.user']);

        $teams = $territory->salesTeams()
            ->with(['memberships' => fn ($q) => $q
                ->where('role', 'seller')
                ->where('status', 'active')
                ->with('user:id,name,email')])
            ->orderBy('name')
            ->get();

        return view('admin.territories.show', compact('territory', 'teams'));
    }

    public function edit(Territory $territory): View
    {
        $territory->load(['market', 'supervisors.user']);

        return view('admin.territories.edit', [
            'territory' => $territory,
            'market' => $territory->market,
            'candidates' => $this->supervisorCandidates(),
        ]);
    }

    public function update(TerritoryRequest $request, Territory $territory): RedirectResponse
    {
        $data = $request->validated();

        $assignment = DB::transaction(function () use ($territory, $data) {
            $territory->update([
                'name' => $data['name'],
                'geo_reference' => $data['geo_reference'] ?? null,
            ]);

            return $this->supervisors->assign($territory, $data);
        });

        return redirect()
            ->route('admin.territories.show', $territory->uuid)
            ->with('status', $this->statusMessage('Territorio actualizado.', $assignment))
            ->with('warning', $this->notifySupervisor($assignment));
    }

    public function destroy(Territory $territory): RedirectResponse
    {
        $blockers = array_keys(array_filter([
            'equipos de venta' => $territory->salesTeams()->exists(),
            'vendedores activos' => $territory->memberships()->where('role', 'seller')->where('status', 'active')->exists(),
        ]));

        if ($blockers !== []) {
            return back()->with('error', 'No se puede eliminar el territorio porque tiene: '.implode(', ', $blockers).'.');
        }

        $market = $territory->market;

        DB::transaction(function () use ($territory) {
            $this->supervisors->retireAll($territory);

            // Datos heredados: gerentes que apuntaban a este territorio.
            Membership::where('territory_id', $territory->id)->where('role', 'manager')->update(['territory_id' => null]);
            $territory->update(['assigned_manager_membership_id' => null]);

            $territory->delete();
        });

        return redirect()
            ->route('admin.markets.show', $market->uuid)
            ->with('status', "Territorio {$territory->name} eliminado.");
    }

    /** Personas que ya operan en el sistema y pueden ser supervisores. */
    private function supervisorCandidates()
    {
        return User::query()
            ->whereHas('memberships', fn ($q) => $q->active()->whereIn('role', ['manager', 'supervisor', 'seller', 'deza_admin']))
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'email']);
    }

    private function statusMessage(string $base, ?array $assignment): string
    {
        return $assignment ? "{$base} Nuevo supervisor: {$assignment['user']->name}." : $base;
    }

    /** Envía el correo ya con la transacción confirmada; si falla, avisa con la contraseña temporal. */
    private function notifySupervisor(?array $assignment): ?string
    {
        if (! $assignment || ! $assignment['password']) {
            return null;
        }

        try {
            $this->invitations->sendWelcomeEmail($assignment['user'], 'supervisor', $assignment['password']);
        } catch (\Throwable $e) {
            report($e);

            return "No se pudo enviar el correo a {$assignment['user']->email}. Contraseña temporal: {$assignment['password']} (compártela de forma segura).";
        }

        return null;
    }
}