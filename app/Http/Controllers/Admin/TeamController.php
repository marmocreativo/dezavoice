<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamRequest;
use App\Models\SalesTeam;
use App\Models\Territory;
use App\Models\User;
use App\Services\PersonInvitationService;
use App\Services\SalesStatsService;
use App\Services\TeamSellersService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(
        private readonly TeamSellersService $sellers,
        private readonly SalesStatsService $stats,
        private readonly PersonInvitationService $invitations,
    ) {}

    public function create(Territory $territory): View
    {
        $territory->load('market');

        return view('admin.teams.create', [
            'territory' => $territory,
            'team' => new SalesTeam(['is_active' => true]),
            'candidates' => $this->candidates(),
            'currentSellers' => collect(),
        ]);
    }

    public function store(TeamRequest $request, Territory $territory): RedirectResponse
    {
        $data = $request->validated();

        [$team, $report] = DB::transaction(function () use ($territory, $data, $request) {
            $team = SalesTeam::create([
                'market_id' => $territory->market_id,
                'territory_id' => $territory->id,
                // Columna heredada que la API y la PWA todavía leen.
                'manager_membership_id' => $territory->market->managers()->value('id'),
                'name' => $data['name'],
                'is_active' => $request->boolean('is_active'),
            ]);

            return [$team, $this->sellers->sync($team, $data)];
        });

        return redirect()
            ->route('admin.teams.show', $team->uuid)
            ->with('status', $this->summary('Equipo creado.', $report))
            ->with('warning', $this->notifyCreated($report['created']));
    }

    public function show(SalesTeam $team): View
    {
        $team->load(['territory.market', 'territory.supervisors.user']);

        $sellers = $team->memberships()
            ->where('role', 'seller')
            ->where('status', 'active')
            ->with('user:id,name,email')
            ->withCount(['sales as sales_count' => fn ($q) => $q->where('status', 'venta_ganada')])
            ->withSum(['sales as sales_cents' => fn ($q) => $q->where('status', 'venta_ganada')], 'amount_cents')
            ->get()
            ->sortBy(fn ($m) => $m->user->name)
            ->values();

        $stats = $this->stats->forSellers($sellers);

        return view('admin.teams.show', compact('team', 'sellers', 'stats'));
    }

    public function edit(SalesTeam $team): View
    {
        $team->load('territory.market');

        $currentSellers = $team->memberships()
            ->where('role', 'seller')
            ->where('status', 'active')
            ->with('user:id,name,email')
            ->get()
            ->sortBy(fn ($m) => $m->user->name)
            ->values();

        return view('admin.teams.edit', [
            'team' => $team,
            'territory' => $team->territory,
            'candidates' => $this->candidates($team),
            'currentSellers' => $currentSellers,
        ]);
    }

    public function update(TeamRequest $request, SalesTeam $team): RedirectResponse
    {
        $data = $request->validated();

        $report = DB::transaction(function () use ($team, $data, $request) {
            $team->update([
                'name' => $data['name'],
                'is_active' => $request->boolean('is_active'),
            ]);

            return $this->sellers->sync($team, $data);
        });

        return redirect()
            ->route('admin.teams.show', $team->uuid)
            ->with('status', $this->summary('Equipo actualizado.', $report))
            ->with('warning', $this->notifyCreated($report['created']));
    }

    public function destroy(SalesTeam $team): RedirectResponse
    {
        $hasSellers = $team->memberships()->where('role', 'seller')->where('status', 'active')->exists();

        if ($hasSellers) {
            return back()->with('error', 'No se puede eliminar un equipo con vendedores activos. Retíralos primero desde "Editar".');
        }

        $territory = $team->territory;
        $team->delete();

        return redirect()
            ->route('admin.territories.show', $territory->uuid)
            ->with('status', "Equipo {$team->name} eliminado.");
    }

    /** Personas que pueden ser vendedores; indica en qué equipo están hoy (se moverán). */
    private function candidates(?SalesTeam $team = null)
    {
        return User::query()
            ->whereHas('memberships', fn ($q) => $q->active()->whereIn('role', ['manager', 'supervisor', 'seller', 'deza_admin']))
            ->with(['memberships' => fn ($q) => $q->active()->where('role', 'seller')->with('salesTeam:id,name')])
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'email'])
            ->reject(fn (User $user) => $team && $user->memberships->contains(fn ($m) => (int) $m->sales_team_id === (int) $team->id))
            ->values();
    }

    private function summary(string $base, array $report): string
    {
        $new = $report['added'] + count($report['created']);

        $parts = array_filter([
            $new ? $new.($new === 1 ? ' vendedor agregado' : ' vendedores agregados') : null,
            $report['moved'] ? $report['moved'].($report['moved'] === 1 ? ' movido desde otro equipo' : ' movidos desde otro equipo') : null,
            $report['removed'] ? $report['removed'].($report['removed'] === 1 ? ' retirado' : ' retirados') : null,
        ]);

        return $parts ? $base.' '.implode(', ', $parts).'.' : $base;
    }

    /** Envía los correos ya con la transacción confirmada; si alguno falla, avisa con la contraseña temporal. */
    private function notifyCreated(array $created): ?string
    {
        $failures = [];

        foreach ($created as $item) {
            try {
                $this->invitations->sendWelcomeEmail($item['user'], 'seller', $item['password']);
            } catch (\Throwable $e) {
                report($e);
                $failures[] = "{$item['user']->email} (contraseña temporal: {$item['password']})";
            }
        }

        return $failures
            ? 'No se pudo enviar el correo a: '.implode('; ', $failures).'. Compártelas de forma segura.'
            : null;
    }
}