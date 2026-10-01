<?php

namespace App\Http\Requests\Admin;

use App\Models\Territory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TerritoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('supervisor_mode', 'keep');

        $this->merge([
            'supervisor_mode' => $mode,
            'supervisor_user_uuid' => $mode === 'existing' ? $this->input('supervisor_user_uuid') : null,
            'supervisor_name' => $mode === 'new' ? $this->input('supervisor_name') : null,
            'supervisor_email' => $mode === 'new' ? Str::lower(trim((string) $this->input('supervisor_email'))) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Territory|null $territory */
        $territory = $this->route('territory');
        $marketId = $territory?->market_id ?? $this->route('market')->id;

        $uniqueName = Rule::unique('territories', 'name')
            ->where('market_id', $marketId)
            ->whereNull('deleted_at');

        if ($territory) {
            $uniqueName->ignore($territory->id);
        }

        return [
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'geo_reference' => ['nullable', 'string', 'max:2000'],

            'supervisor_mode' => ['required', Rule::in(['keep', 'existing', 'new'])],
            'supervisor_user_uuid' => ['required_if:supervisor_mode,existing', 'nullable', 'exists:users,uuid'],
            'supervisor_name' => ['required_if:supervisor_mode,new', 'nullable', 'string', 'max:255'],
            'supervisor_email' => ['required_if:supervisor_mode,new', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'El campo :attribute es obligatorio.',
            'string' => ':Attribute debe ser texto.',
            'max' => ':Attribute no puede superar :max caracteres.',
            'unique' => ':Attribute ya está en uso.',
            'email' => 'Escribe un correo electrónico válido.',
            'in' => ':Attribute no es válido.',
            'exists' => 'La persona seleccionada no existe.',
            'name.unique' => 'Ya existe un territorio con ese nombre en este mercado.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'geo_reference' => 'referencia geográfica',
            'supervisor_mode' => 'opción de supervisor',
            'supervisor_user_uuid' => 'persona',
            'supervisor_name' => 'nombre del supervisor',
            'supervisor_email' => 'correo del supervisor',
        ];
    }
}