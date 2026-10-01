<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTerritoryRequest extends FormRequest
{
    public const FIXED_NAMES = ['Norte', 'Sur', 'Este', 'Oeste', 'Centro'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'market_uuid' => ['required', 'string', 'exists:markets,uuid'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}