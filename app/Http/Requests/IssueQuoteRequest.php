<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_uuid' => ['required', 'string', 'exists:plans,uuid'],
            'organization_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['required', 'string', 'max:50'],
            'billing_email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email'],
        ];
    }
}