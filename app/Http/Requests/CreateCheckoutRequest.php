<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'success_url' => ['nullable', 'url'],
            'cancel_url' => ['nullable', 'url'],
        ];
    }
}