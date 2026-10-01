<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $market = $this->route('market');

        return [
            'code' => ['sometimes', 'string', 'size:2', Rule::unique('markets', 'code')->ignore($market->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}