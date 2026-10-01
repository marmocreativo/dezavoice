<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\CommissionRule;
use App\Models\Market;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function create(Market $market): View
    {
        return view('admin.plans.create', [
            'market' => $market,
            'plan' => new Plan(),
        ]);
    }

    public function store(PlanRequest $request, Market $market): RedirectResponse
    {
        $data = $request->validated();

        Plan::create([
            'market_id' => $market->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'price_cents' => $this->toCents($data['price']),
            'setup_fee_cents' => $this->toCents($data['setup_fee'] ?? 0),
            'currency' => $market->currency,
            'minutos_mensuales' => $data['minutos_mensuales'],
            'billing_period' => 'monthly', // CheckoutService cobra siempre por mes
        ]);

        return redirect()
            ->to(route('admin.markets.show', $market->uuid).'#planes')
            ->with('status', "Plan {$data['name']} creado.");
    }

    public function edit(Plan $plan): View
    {
        $plan->load('market');

        return view('admin.plans.edit', [
            'plan' => $plan,
            'market' => $plan->market,
            'inUse' => $plan->opportunities()->exists() || $plan->subscriptions()->exists(),
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $data = $request->validated();

        $plan->update([
            'minutos_mensuales' => $data['minutos_mensuales'],
            'name' => $data['name'],
            'price_cents' => $this->toCents($data['price']),
            'setup_fee_cents' => $this->toCents($data['setup_fee'] ?? 0),
        ]);

        return redirect()
            ->to(route('admin.markets.show', $plan->market->uuid).'#planes')
            ->with('status', "Plan {$plan->name} actualizado.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $usage = array_filter([
            'cotizaciones' => $plan->opportunities()->count(),
            'suscripciones' => $plan->subscriptions()->count(),
            'reglas de comisión' => CommissionRule::where('plan_id', $plan->id)->count(),
        ]);

        if ($usage !== []) {
            $detail = collect($usage)->map(fn ($count, $label) => "{$label}: {$count}")->implode(', ');

            return back()->with('error', "No se puede eliminar el plan {$plan->name} porque está en uso ({$detail}).");
        }

        $market = $plan->market;
        $plan->delete();

        return redirect()
            ->to(route('admin.markets.show', $market->uuid).'#planes')
            ->with('status', "Plan {$plan->name} eliminado.");
    }

    /** Los precios se capturan en unidades de moneda y se guardan en centavos. */
    private function toCents(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}