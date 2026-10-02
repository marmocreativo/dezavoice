<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanCommissionRequest;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\CommissionRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlanCommissionController extends Controller
{
    private const RESULT_LABELS = ['unchanged' => 'sin cambios', 'updated' => 'actualizada', 'created' => 'nueva versión'];

    public function __construct(private readonly CommissionRuleService $rules) {}

    public function update(PlanCommissionRequest $request, Plan $plan): RedirectResponse
    {
        $data = $request->validated();
        $effectiveFrom = Carbon::parse($data['effective_from']);

        $results = [];
        $payloads = [];

        try {
            DB::transaction(function () use ($plan, $data, $effectiveFrom, &$results, &$payloads) {
                foreach (CommissionRuleService::ROLES as $role => $label) {
                    $row = $data['roles'][$role] ?? [];
                    $payloads[$role] = ($row['enabled'] ?? '0') === '1' ? $this->payload($row) : null;
                    $results[$role] = $this->rules->save($plan, $role, $payloads[$role], $effectiveFrom);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $changed = collect($results)->contains(fn ($result) => $result !== 'unchanged');

        if (! $changed) {
            return redirect()
                ->to(route('admin.plans.edit', $plan->uuid).'#comisiones')
                ->with('status', 'No hubo cambios en las comisiones.');
        }

        AuditLog::record(
            actor: $request->user()->adminMembership(),
            action: 'commission_rules.updated',
            subject: $plan,
            before: [],
            after: ['effective_from' => $effectiveFrom->toDateString(), 'results' => $results, 'rules' => $payloads],
        );

        $summary = collect($results)
            ->map(fn ($result, $role) => CommissionRuleService::ROLES[$role].': '.self::RESULT_LABELS[$result])
            ->implode(' · ');

        return redirect()
            ->to(route('admin.plans.edit', $plan->uuid).'#comisiones')
            ->with('status', "Comisiones guardadas. {$summary}.");
    }

    /** @return array<string, int|float|null> */
    private function payload(array $row): array
    {
        $int = fn ($value) => filled($value) ? (int) $value : null;

        return [
            'activation_amount_cents' => (int) round(((float) $row['activation']) * 100),
            'recurring_percentage' => filled($row['recurring_percentage'] ?? null) ? (float) $row['recurring_percentage'] : null,
            'duration_months' => $int($row['duration_months'] ?? null),
            'reversal_window_days' => (int) ($row['reversal_window_days'] ?? 0),
            'waiting_period_days' => (int) ($row['waiting_period_days'] ?? 0),
            'individual_goal' => $int($row['individual_goal'] ?? null),
            'team_goal' => $int($row['team_goal'] ?? null),
        ];
    }
}