<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['call', 'message', 'visit', 'note'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
            'checkin_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'checkin_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}