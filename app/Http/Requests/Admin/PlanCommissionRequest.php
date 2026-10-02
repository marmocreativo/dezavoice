<?php

namespace App\Http\Requests\Admin;

use App\Services\CommissionRuleService;
use Illuminate\Foundation\Http\FormRequest;

class PlanCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    protected function prepareForValidation(): void
    {
        $roles = (array) $this->input('roles', []);

        foreach (array_keys(CommissionRuleService::ROLES) as $role) {
            $row = (array) ($roles[$role] ?? []);

            // Un rol apagado no valida ni guarda nada más.
            if (($row['enabled'] ?? '0') !== '1') {
                $roles[$role] = ['enabled' => '0'];

                continue;
            }

            // Sin porcentaje (o en 0) no hay comisión recurrente.
            if (! filled($row['recurring_percentage'] ?? null) || (float) $row['recurring_percentage'] <= 0) {
                $row['recurring_percentage'] = null;
                $row['duration_months'] = null;
            }

            $roles[$role] = $row;
        }

        $this->merge(['roles' => $roles]);
    }

    public function rules(): array
    {
        $rules = ['effective_from' => ['required', 'date', 'after_or_equal:yesterday']];

        foreach (array_keys(CommissionRuleService::ROLES) as $role) {
            $p = "roles.{$role}";

            $rules["{$p}.enabled"] = ['nullable', 'boolean'];
            $rules["{$p}.activation"] = ["required_if:{$p}.enabled,1", 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'];
            $rules["{$p}.recurring_percentage"] = ['nullable', 'numeric', 'decimal:0,2', 'between:0,100'];
            $rules["{$p}.duration_months"] = ["required_with:{$p}.recurring_percentage", 'nullable', 'integer', 'min:1', 'max:120'];
            $rules["{$p}.reversal_window_days"] = ['nullable', 'integer', 'min:0', 'max:3650'];
            $rules["{$p}.waiting_period_days"] = ['nullable', 'integer', 'min:0', 'max:3650'];
            $rules["{$p}.individual_goal"] = ['nullable', 'integer', 'min:0', 'max:100000'];
            $rules["{$p}.team_goal"] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'El campo :attribute es obligatorio.',
            'required_with' => 'Indica por cuántos meses aplica el porcentaje (:attribute).',
            'numeric' => ':Attribute debe ser un número.',
            'integer' => ':Attribute debe ser un número entero.',
            'decimal' => ':Attribute admite como máximo 2 decimales.',
            'min' => ':Attribute no puede ser menor que :min.',
            'max' => ':Attribute no puede superar :max.',
            'between' => ':Attribute debe estar entre :min y :max.',
            'date' => ':Attribute no es una fecha válida.',
            'after_or_equal' => 'La vigencia no puede ser una fecha pasada.',
        ];
    }

    public function attributes(): array
    {
        $names = [];

        foreach (CommissionRuleService::ROLES as $role => $label) {
            $names["roles.{$role}.activation"] = "comisión de activación de {$label}";
            $names["roles.{$role}.recurring_percentage"] = "porcentaje recurrente de {$label}";
            $names["roles.{$role}.duration_months"] = "meses del porcentaje de {$label}";
            $names["roles.{$role}.reversal_window_days"] = "ventana de reversión de {$label}";
            $names["roles.{$role}.waiting_period_days"] = "días de espera de {$label}";
            $names["roles.{$role}.individual_goal"] = "meta individual de {$label}";
            $names["roles.{$role}.team_goal"] = "meta de equipo de {$label}";
        }

        return $names + ['effective_from' => 'vigencia'];
    }
}