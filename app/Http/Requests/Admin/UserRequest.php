<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('password_mode', 'keep');

        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'password_mode' => $mode,
            'password' => $mode === 'manual' ? $this->input('password') : null,
            'password_confirmation' => $mode === 'manual' ? $this->input('password_confirmation') : null,
        ]);
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'must_change_password' => ['nullable', 'boolean'],
            'password_mode' => ['required', Rule::in(['keep', 'generate', 'manual'])],
            'password' => ['required_if:password_mode,manual', 'nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'El campo :attribute es obligatorio.',
            'string' => ':Attribute debe ser texto.',
            'max' => ':Attribute no puede superar :max caracteres.',
            'email' => 'Escribe un correo electrónico válido.',
            'unique' => 'Ese correo ya lo usa otra persona.',
            'in' => ':Attribute no es válido.',
            'confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'password' => 'contraseña',
            'password_mode' => 'opción de contraseña',
        ];
    }
}