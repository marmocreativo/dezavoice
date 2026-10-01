<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => Str::lower(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        /** @var Plan|null $plan */
        $plan = $this->route('plan');
        $marketId = $plan?->market_id ?? $this->route('market')->id;

        return [
            // El código se define al crear y no se edita.
            'code' => $plan
                ? ['prohibited']
                : [
                    'required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/',
                    Rule::unique('plans', 'code')->where('market_id', $marketId)->whereNull('deleted_at'),
                ],
            'name' => ['required', 'string', 'max:255'],
            'minutos_mensuales' => ['required', 'integer', 'min:1', 'max:10000000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'setup_fee' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => ':Attribute debe ser texto.',
            'max' => ':Attribute no puede superar :max.',
            'min' => ':Attribute debe ser como mínimo :min.',
            'numeric' => ':Attribute debe ser un número.',
            'integer' => ':Attribute debe ser un número entero.',
            'decimal' => ':Attribute admite como máximo 2 decimales.',
            'regex' => 'El código solo puede tener minúsculas, números, guiones y guion bajo.',
            'code.unique' => 'Ya existe un plan con ese código en este mercado.',
            'prohibited' => 'El código no se puede modificar.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código',
            'name' => 'nombre',
            'minutos_mensuales' => 'minutos mensuales',
            'price' => 'precio mensual',
            'setup_fee' => 'costo de configuración',
        ];
    }
}