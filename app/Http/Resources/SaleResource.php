<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'status' => $this->status,
            'validated_at' => $this->validated_at?->toIso8601String(),
            'business_name' => $this->opportunity?->prospect?->business_name,
            'plan_name' => $this->opportunity?->subscriptionPlan?->name,
            'seller_name' => $this->seller?->user?->name,
            'seller_codigo' => $this->seller?->codigo,
        ];
    }
}