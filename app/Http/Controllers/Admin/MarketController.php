<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarketRequest;
use App\Models\Market;
use App\Models\Plan;
use App\Models\SalesProspect;
use App\Models\User;
use App\Services\MarketManagerService;
use App\Services\PersonInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarketController extends Controller
{
    public function __construct(
        private readonly MarketManagerService $managers,
        private readonly PersonInvitationService $invitations,
    ) {}

    public function index(): View
    {
        $markets = Market::query()
            ->with('managers.user')
            ->withCount('territories')
            ->orderBy('name')
            ->get();

        return view('admin.markets.index', compact('markets'));
    }

    public function create(): View
    {
        return view('admin.markets.create', [
            'market' => new Market(['is_active' => true, 'tax_rate' => 0]),
            'candidates' => $this->managerCandidates(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function store(MarketRequest $request): RedirectResponse
    {
        $data = $request->validated();

        [$market, $assignment] = DB::transaction(function () use ($data, $request) {
            $market = Market::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'currency' => $data['currency'],
                'timezone' => $data['timezone'],
                'tax_name' => $data['tax_name'] ?? null,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'is_active' => $request->boolean('is_active'),
            ]);

            return [$market, $this->managers->assign($market, $data)];
        });

        return redirect()
            ->route('admin.markets.show', $market->uuid)
            ->with('status', $this->statusMessage('Mercado creado.', $assignment))
            ->with('warning', $this->notifyManager($assignment));
    }

    public function show(Market $market): View
    {
        $market->load('managers.user')->loadCount(['territories', 'salesTeams']);

        $territories = $market->territories()
            ->with('supervisors.user')
            ->withCount([
                'salesTeams',
                'memberships as sellers_count' => fn ($q) => $q->where('role', 'seller')->where('status', 'active'),
            ])
            ->orderBy('name')
            ->get();

        $plans = $market->plans()
            ->withCount(['opportunities', 'subscriptions'])
            ->orderBy('price_cents')
            ->get();

        return view('admin.markets.show', compact('market', 'territories', 'plans'));
    }

    public function edit(Market $market): View
    {
        $market->load('managers.user');

        return view('admin.markets.edit', [
            'market' => $market,
            'candidates' => $this->managerCandidates(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(MarketRequest $request, Market $market): RedirectResponse
    {
        $data = $request->validated();

        $assignment = DB::transaction(function () use ($market, $data, $request) {
            $market->update([
                'name' => $data['name'],
                'currency' => $data['currency'],
                'timezone' => $data['timezone'],
                'tax_name' => $data['tax_name'] ?? null,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'is_active' => $request->boolean('is_active'),
            ]);

            return $this->managers->assign($market, $data);
        });

        return redirect()
            ->route('admin.markets.show', $market->uuid)
            ->with('status', $this->statusMessage('Mercado actualizado.', $assignment))
            ->with('warning', $this->notifyManager($assignment));
    }

    public function destroy(Market $market): RedirectResponse
    {
        $blockers = $this->deletionBlockers($market);

        if ($blockers !== []) {
            return back()->with('error', 'No se puede eliminar el mercado porque tiene: '.implode(', ', $blockers).'.');
        }

        DB::transaction(function () use ($market) {
            $market->managers()->update(['status' => 'inactive', 'ended_at' => now()]);
            $market->delete();
        });

        return redirect()
            ->route('admin.markets.index')
            ->with('status', "Mercado {$market->name} eliminado.");
    }

    /** Personas que ya operan en el sistema y pueden ser gerentes. */
    private function managerCandidates()
    {
        return User::query()
            ->whereHas('memberships', fn ($q) => $q->active()->whereIn('role', ['manager', 'supervisor', 'seller', 'deza_admin']))
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'email']);
    }

    /** @return list<string> */
    private function deletionBlockers(Market $market): array
    {
        return array_keys(array_filter([
            'territorios' => $market->territories()->exists(),
            'equipos de venta' => $market->salesTeams()->exists(),
            'organizaciones' => $market->organizations()->exists(),
            'planes' => Plan::where('market_id', $market->id)->exists(),
            'prospectos' => SalesProspect::where('market_id', $market->id)->exists(),
            'personas con membresía activa' => $market->memberships()->active()->where('role', '!=', 'manager')->exists(),
        ]));
    }

    private function statusMessage(string $base, ?array $assignment): string
    {
        return $assignment ? "{$base} Nuevo gerente: {$assignment['user']->name}." : $base;
    }

    /** Envía el correo ya con la transacción confirmada; si falla, avisa con la contraseña temporal. */
    private function notifyManager(?array $assignment): ?string
    {
        if (! $assignment || ! $assignment['password']) {
            return null;
        }

        try {
            $this->invitations->sendWelcomeEmail($assignment['user'], 'manager', $assignment['password']);
        } catch (\Throwable $e) {
            report($e);

            return "No se pudo enviar el correo a {$assignment['user']->email}. Contraseña temporal: {$assignment['password']} (compártela de forma segura).";
        }

        return null;
    }
}