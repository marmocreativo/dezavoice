<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubscriptionRequest;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public const STATUSES = [
        'pending_payment' => 'Pago pendiente',
        'active' => 'Activa',
        'cancelled' => 'Cancelada',
    ];

    public function edit(Subscription $subscription): View
    {
        $subscription->load(['plan', 'organization', 'opportunity.prospect']);

        $prospect = $subscription->opportunity?->prospect;
        $marketId = $subscription->plan?->market_id ?? $prospect?->market_id;

        $statuses = self::STATUSES;
        if (! isset($statuses[$subscription->status])) {
            $statuses[$subscription->status] = \Illuminate\Support\Str::headline($subscription->status);
        }

        return view('admin.subscriptions.edit', [
            'subscription' => $subscription,
            'prospect' => $prospect,
            'plans' => Plan::where('market_id', $marketId)->orderBy('price_cents')->get(),
            'statuses' => $statuses,
        ]);
    }

    public function update(SubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validated();
        $plan = Plan::where('uuid', $data['plan_uuid'])->firstOrFail();
        $planChanged = $plan->id !== $subscription->plan_id;
        $statusChanged = $data['status'] !== $subscription->status;
        $actor = $request->user()->adminMembership();

        DB::transaction(function () use ($subscription, $data, $plan, $planChanged, $actor) {
            $subscription->fill([
                'plan_id' => $plan->id,
                'status' => $data['status'],
                // Si cambia el plan, la suscripción toma los minutos del nuevo plan; si no, se respeta lo capturado.
                'minutos_mensuales' => $planChanged ? $plan->minutos_mensuales : $data['minutos_mensuales'],
                'minutos_utilizados' => $data['minutos_utilizados'],
                'started_at' => $data['started_at'] ?? null,
                'current_period_end' => $data['current_period_end'] ?? null,
                // Al reactivar se limpia la cancelación; al cancelar sin fecha se usa hoy.
                'canceled_at' => $data['status'] === 'cancelled' ? ($data['canceled_at'] ?? now()) : null,
            ]);

            $dirty = $subscription->getDirty();
            $before = array_intersect_key($subscription->getOriginal(), $dirty);
            $subscription->save();

            // Las comisiones resuelven la regla con opportunity.plan_id: se mantienen alineados.
            if ($planChanged) {
                Opportunity::whereKey($subscription->opportunity_id)->update(['plan_id' => $plan->id]);
            }

            if ($dirty !== []) {
                AuditLog::record(
                    actor: $actor,
                    action: 'subscription.updated',
                    subject: $subscription,
                    before: $before,
                    after: $dirty,
                );
            }
        });

        $prospect = $subscription->opportunity?->prospect;
        $message = 'Plan y suscripción actualizados.';

        if ($planChanged || $statusChanged) {
            $message .= ' Usa "Revisar comisiones" para validar que los pagos y las comisiones sigan siendo correctos.';
        }

        return redirect()
            ->to($prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index'))
            ->with('status', $message);
    }
}