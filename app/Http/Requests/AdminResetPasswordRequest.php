<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['manual', 'auto'])],
            'password' => ['required_if:mode,manual', 'nullable', 'string', 'min:8'],
        ];
    }
}