<?php

namespace App\Services;

use App\Models\CommissionLedger;
use App\Models\CommissionPlan;
use App\Models\CommissionRule;
use App\Models\Plan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommissionRuleService
{
    public const ROLES = ['seller' => 'Vendedor', 'supervisor' => 'Supervisor', 'manager' => 'Gerente'];

    /**
     * Lo que cobra cada rol por este plan hoy, el historial de versiones
     * y cuántas reglas genéricas (sin plan) hay en el mercado.
     *
     * @return array{roles: array<string, array<string, mixed>>, history: \Illuminate\Support\Collection, generic: int}
     */
    public function overview(Plan $plan): array
    {
        $today = now()->toDateString();
        $roles = [];

        foreach (self::ROLES as $role => $label) {
            $version = CommissionPlan::query()
                ->where('market_id', $plan->market_id)
                ->where('role', $role)
                ->effectiveOn($today)
                ->orderByDesc('version')
                ->first();

            $roles[$role] = [
                'label' => $label,
                'version' => $version,
                'rule' => $version?->rules()->where('plan_id', $plan->id)->first(),
            ];
        }

        $order = array_keys(self::ROLES);

        $history = CommissionRule::query()
            ->where('plan_id', $plan->id)
            ->with('commissionPlan')
            ->get()
            ->filter(fn (CommissionRule $rule) => $rule->commissionPlan !== null)
            ->sort(function (CommissionRule $a, CommissionRule $b) use ($order) {
                return [array_search($a->commissionPlan->role, $order), $b->commissionPlan->version]
                    <=> [array_search($b->commissionPlan->role, $order), $a->commissionPlan->version];
            })
            ->values();

        $generic = CommissionRule::query()
            ->whereNull('plan_id')
            ->whereHas('commissionPlan', fn ($q) => $q->where('market_id', $plan->market_id))
            ->count();

        return ['roles' => $roles, 'history' => $history, 'generic' => $generic];
    }

    /**
     * Guarda lo que cobra un rol por un plan. `$data = null` significa "este rol no cobra por este plan".
     *
     * @param  array<string, int|float|null>|null  $data
     * @return 'unchanged'|'updated'|'created'
     */
    public function save(Plan $plan, string $role, ?array $data, CarbonInterface $effectiveFrom): string
    {
        $latest = CommissionPlan::query()
            ->where('market_id', $plan->market_id)
            ->where('role', $role)
            ->orderByDesc('version')
            ->first();

        $existing = $latest?->rules()->where('plan_id', $plan->id)->first();

        if ($this->sameAs($existing, $data)) {
            return 'unchanged';
        }

        $from = $effectiveFrom->copy()->startOfDay();
        $label = self::ROLES[$role] ?? $role;

        if ($latest && $from->lt($latest->effective_from->copy()->startOfDay())) {
            throw new RuntimeException("{$label}: la vigencia no puede ser anterior al inicio de su versión actual ({$latest->effective_from->format('d/m/Y')}).");
        }

        return DB::transaction(function () use ($plan, $role, $data, $latest, $from, $label) {
            // Misma fecha de inicio: se corrige en el lugar si todavía no generó movimientos.
            if ($latest && $latest->effective_from->isSameDay($from)) {
                if ($this->hasLedger($latest)) {
                    throw new RuntimeException("{$label}: ya hay comisiones generadas con la versión que empezó ese día. Elige una fecha de vigencia posterior.");
                }

                $this->apply($latest, $plan, $data);

                return 'updated';
            }

            $version = ($latest?->version ?? 0) + 1;

            $new = CommissionPlan::create([
                'market_id' => $plan->market_id,
                'role' => $role,
                'name' => sprintf('Plan %s %d v%d', $plan->market->name, $from->year, $version),
                'version' => $version,
                'effective_from' => $from->toDateString(),
                'effective_to' => null,
            ]);

            if ($latest) {
                // La versión anterior termina el día antes; sus reglas y asientos quedan intactos.
                $closeAt = $from->copy()->subDay()->toDateString();

                if ($latest->effective_to === null || $latest->effective_to->toDateString() > $closeAt) {
                    $latest->update(['effective_to' => $closeAt]);
                }

                // Las reglas de los demás planes pasan tal cual a la versión nueva.
                foreach ($latest->rules as $rule) {
                    if ((int) $rule->plan_id === (int) $plan->id) {
                        continue;
                    }

                    $copy = $rule->replicate(['uuid']);
                    $copy->commission_plan_id = $new->id;
                    $copy->save();
                }
            }

            $this->apply($new, $plan, $data);

            return 'created';
        });
    }

    private function apply(CommissionPlan $version, Plan $plan, ?array $data): void
    {
        $rule = $version->rules()->where('plan_id', $plan->id)->first();

        if ($data === null) {
            $rule?->delete();

            return;
        }

        if ($rule) {
            $rule->update($data);

            return;
        }

        CommissionRule::create($data + ['commission_plan_id' => $version->id, 'plan_id' => $plan->id]);
    }

    private function hasLedger(CommissionPlan $version): bool
    {
        $ruleIds = CommissionRule::withTrashed()->where('commission_plan_id', $version->id)->pluck('id');

        return CommissionLedger::whereIn('commission_rule_id', $ruleIds)->exists();
    }

    private function sameAs(?CommissionRule $rule, ?array $data): bool
    {
        if ($rule === null || $data === null) {
            return $rule === null && $data === null;
        }

        foreach ($data as $key => $value) {
            $current = $rule->{$key} === null ? null : (float) $rule->{$key};
            $new = $value === null ? null : (float) $value;

            if ($current !== $new) {
                return false;
            }
        }

        return true;
    }
}