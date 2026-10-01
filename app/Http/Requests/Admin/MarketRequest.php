<?php

namespace App\Http\Requests\Admin;

use App\Models\Market;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('manager_mode', 'keep');

        $this->merge([
            'manager_mode' => $mode,
            'manager_user_uuid' => $mode === 'existing' ? $this->input('manager_user_uuid') : null,
            'manager_name' => $mode === 'new' ? $this->input('manager_name') : null,
            'manager_email' => $mode === 'new' ? Str::lower(trim((string) $this->input('manager_email'))) : null,
            'currency' => Str::upper(trim((string) $this->input('currency'))),
        ]);

        if ($this->has('code')) {
            $this->merge(['code' => Str::upper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        /** @var Market|null $market */
        $market = $this->route('market');

        return [
            // El código se define al crear y no se edita.
            'code' => $market
                ? ['prohibited']
                : ['required', 'string', 'size:2', 'alpha', Rule::unique('markets', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'tax_name' => ['nullable', 'string', 'max:20'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'is_active' => ['nullable', 'boolean'],

            'manager_mode' => ['required', Rule::in(['keep', 'existing', 'new'])],
            'manager_user_uuid' => ['required_if:manager_mode,existing', 'nullable', 'exists:users,uuid'],
            'manager_name' => ['required_if:manager_mode,new', 'nullable', 'string', 'max:255'],
            'manager_email' => ['required_if:manager_mode,new', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'El campo :attribute es obligatorio.',
            'string' => ':Attribute debe ser texto.',
            'max' => ':Attribute no puede superar :max caracteres.',
            'size' => ':Attribute debe tener :size caracteres.',
            'alpha' => ':Attribute solo puede contener letras.',
            'unique' => ':Attribute ya está en uso.',
            'email' => 'Escribe un correo electrónico válido.',
            'numeric' => ':Attribute debe ser un número.',
            'between' => ':Attribute debe estar entre :min y :max.',
            'in' => ':Attribute no es válido.',
            'exists' => 'La persona seleccionada no existe.',
            'prohibited' => 'El código no se puede modificar.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'código',
            'name' => 'nombre',
            'currency' => 'moneda',
            'timezone' => 'zona horaria',
            'tax_name' => 'nombre del impuesto',
            'tax_rate' => 'tasa de impuesto',
            'manager_mode' => 'opción de gerente',
            'manager_user_uuid' => 'persona',
            'manager_name' => 'nombre del gerente',
            'manager_email' => 'correo del gerente',
        ];
    }
}