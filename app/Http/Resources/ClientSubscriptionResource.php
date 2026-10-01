<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'organization' => [
                'uuid' => $this->organization->uuid,
                'name' => $this->organization->name,
                'status' => $this->organization->status,
            ],
            'plan' => [
                'name' => $this->plan->name,
                'price_cents' => $this->plan->price_cents,
                'currency' => $this->plan->currency,
                'billing_period' => $this->plan->billing_period,
            ],
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
        ];
    }
}