<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\SubscriptionController;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    public function rules(): array
    {
        /** @var Subscription $subscription */
        $subscription = $this->route('subscription');
        $marketId = $subscription->plan?->market_id ?? $subscription->opportunity?->prospect?->market_id;

        $periodEnd = ['nullable', 'date'];
        if ($this->filled('started_at')) {
            $periodEnd[] = 'after_or_equal:started_at';
        }

        return [
            // Solo planes del mismo mercado.
            'plan_uuid' => ['required', Rule::exists('plans', 'uuid')->where('market_id', $marketId)->whereNull('deleted_at')],
            // Se conserva el estado actual aunque sea uno que el formulario no ofrece.
            'status' => ['required', Rule::in(array_unique([...array_keys(SubscriptionController::STATUSES), $subscription->status]))],
            'started_at' => ['nullable', 'date'],
            'current_period_end' => $periodEnd,
            'canceled_at' => ['nullable', 'date'],
            'minutos_mensuales' => ['required', 'integer', 'min:0', 'max:10000000'],
            'minutos_utilizados' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'date' => ':Attribute no es una fecha válida.',
            'after_or_equal' => ':Attribute no puede ser anterior al inicio.',
            'in' => ':Attribute no es válido.',
            'exists' => 'El plan seleccionado no es válido para este mercado.',
            'integer' => ':Attribute debe ser un número entero.',
            'numeric' => ':Attribute debe ser un número.',
            'decimal' => ':Attribute admite como máximo 2 decimales.',
            'min' => ':Attribute no puede ser negativo.',
            'max' => ':Attribute es demasiado grande.',
        ];
    }

    public function attributes(): array
    {
        return [
            'plan_uuid' => 'plan',
            'status' => 'estado',
            'started_at' => 'fecha de inicio',
            'current_period_end' => 'fin del periodo actual',
            'canceled_at' => 'fecha de cancelación',
            'minutos_mensuales' => 'minutos mensuales',
            'minutos_utilizados' => 'minutos utilizados',
        ];
    }
}