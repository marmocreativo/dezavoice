<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'price_cents' => $this->price_cents,
            'setup_fee_cents' => $this->setup_fee_cents,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period,
            'market' => new MarketResource($this->whenLoaded('market')),
        ];
    }
}