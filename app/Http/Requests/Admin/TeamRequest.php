<?php

namespace App\Http\Requests\Admin;

use App\Models\SalesTeam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    protected function prepareForValidation(): void
    {
        // Las filas vacías del formulario se ignoran.
        $newSellers = collect($this->input('new_sellers', []))
            ->filter(fn ($row) => is_array($row) && (filled($row['name'] ?? null) || filled($row['email'] ?? null)))
            ->map(fn ($row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'email' => Str::lower(trim((string) ($row['email'] ?? ''))),
            ])
            ->values()
            ->all();

        $this->merge([
            'new_sellers' => $newSellers,
            'existing_sellers' => array_values(array_filter((array) $this->input('existing_sellers', []))),
            'remove_sellers' => array_values(array_filter((array) $this->input('remove_sellers', []))),
        ]);
    }

    public function rules(): array
    {
        /** @var SalesTeam|null $team */
        $team = $this->route('team');
        $territoryId = $team?->territory_id ?? $this->route('territory')->id;

        $uniqueName = Rule::unique('sales_teams', 'name')
            ->where('territory_id', $territoryId)
            ->whereNull('deleted_at');

        if ($team) {
            $uniqueName->ignore($team->id);
        }

        return [
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'is_active' => ['nullable', 'boolean'],

            'remove_sellers' => ['array'],
            'remove_sellers.*' => ['string', 'exists:memberships,uuid'],

            'existing_sellers' => ['array'],
            'existing_sellers.*' => ['string', 'distinct', 'exists:users,uuid'],

            'new_sellers' => ['array'],
            'new_sellers.*.name' => ['required', 'string', 'max:255'],
            'new_sellers.*.email' => ['required', 'email', 'max:255', 'distinct', Rule::unique('users', 'email')],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => ':Attribute debe ser texto.',
            'max' => ':Attribute no puede superar :max caracteres.',
            'unique' => ':Attribute ya está en uso.',
            'email' => 'Escribe un correo electrónico válido.',
            'distinct' => ':Attribute está repetido.',
            'exists' => 'La persona seleccionada no existe.',
            'name.unique' => 'Ya existe un equipo con ese nombre en este territorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'new_sellers.*.name' => 'nombre del vendedor',
            'new_sellers.*.email' => 'correo del vendedor',
            'existing_sellers.*' => 'persona',
        ];
    }
}