<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['manager', 'supervisor', 'seller'])],
            'market_uuid' => ['nullable', 'string', 'exists:markets,uuid'],
            'territory_uuid' => ['nullable', 'string', 'exists:territories,uuid'],
            'sales_team_uuid' => ['nullable', 'string', 'exists:sales_teams,uuid'],
        ];
    }
}