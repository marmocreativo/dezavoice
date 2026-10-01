<?php

namespace App\Http\Requests\Admin;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    protected function prepareForValidation(): void
    {
        $users = collect($this->input('users', []))
            ->map(fn ($user) => [
                'name' => trim((string) ($user['name'] ?? '')),
                'email' => Str::lower(trim((string) ($user['email'] ?? ''))),
            ])
            ->all();

        $this->merge([
            'users' => $users,
            'country' => $this->filled('country') ? Str::upper(trim((string) $this->input('country'))) : null,
            'billing_email' => $this->filled('billing_email') ? Str::lower(trim((string) $this->input('billing_email'))) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'alpha', 'size:2'],
        ];

        $clientUsers = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('role', 'client')->where('organization_id', $organization->id))
            ->get(['id', 'uuid']);

        foreach ($clientUsers as $user) {
            $rules["users.{$user->uuid}.name"] = ['required', 'string', 'max:255'];
            $rules["users.{$user->uuid}.email"] = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => ':Attribute debe ser texto.',
            'max' => ':Attribute no puede superar :max caracteres.',
            'email' => 'Escribe un correo electrónico válido.',
            'alpha' => ':Attribute solo puede contener letras.',
            'size' => ':Attribute debe tener :size caracteres.',
            'unique' => 'Ese correo ya lo usa otra persona.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'legal_name' => 'razón social',
            'tax_id' => 'ID fiscal',
            'billing_email' => 'correo de facturación',
            'phone' => 'teléfono',
            'address_line1' => 'dirección',
            'city' => 'ciudad',
            'state' => 'estado',
            'postal_code' => 'código postal',
            'country' => 'país',
            'users.*.name' => 'nombre del usuario',
            'users.*.email' => 'correo del usuario',
        ];
    }
}