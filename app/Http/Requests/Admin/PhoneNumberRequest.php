<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number' => $this->e164($this->input('phone_number')),
            'forwarded_from' => $this->e164($this->input('forwarded_from')),
        ]);
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('client_phone_numbers', 'phone_number')],
            'label' => ['nullable', 'string', 'max:100'],
            'forwarded_from' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
            'retell_agent_id' => ['nullable', 'string', 'max:100', 'starts_with:agent_'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'regex' => ':Attribute debe estar en formato internacional, por ejemplo +12137771235.',
            'unique' => 'Ese número ya está asignado a un cliente.',
            'string' => ':Attribute debe ser texto.',
            'max' => ':Attribute no puede superar :max caracteres.',
            'starts_with' => 'El ID del agente debe empezar con agent_.',
        ];
    }

    public function attributes(): array
    {
        return [
            'phone_number' => 'número',
            'label' => 'etiqueta',
            'forwarded_from' => 'línea original',
            'retell_agent_id' => 'agente',
            'notes' => 'notas',
        ];
    }

    private function e164(mixed $value): ?string
    {
        $clean = preg_replace('/[^\d+]/', '', (string) $value);

        if ($clean === '' || $clean === null) {
            return null;
        }

        return str_starts_with($clean, '+') ? $clean : '+'.$clean;
    }
}