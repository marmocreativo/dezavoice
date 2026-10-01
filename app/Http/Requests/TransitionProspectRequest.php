<?php

namespace App\Http\Requests;

use App\Models\SalesProspect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_status' => [
                'required',
                Rule::in(SalesProspect::STATUSES),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
            'lost_reason' => ['required_if:to_status,perdido', 'nullable', 'string', 'max:255'],
            'next_action_at' => ['required_if:to_status,pausado', 'nullable', 'date', 'after:now'],
        ];
    }
}