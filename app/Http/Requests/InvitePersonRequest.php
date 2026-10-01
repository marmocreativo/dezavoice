<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvitePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['manager', 'supervisor', 'seller'])],
            'territory_uuid' => ['required_if:role,manager', 'nullable', 'exists:territories,uuid'],
            'sales_team_uuid' => ['required_if:role,supervisor,seller', 'nullable', 'exists:sales_teams,uuid'],
        ];
    }
}