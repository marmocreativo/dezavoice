<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'tax_name' => $this->tax_name,
            'tax_rate' => (float) $this->tax_rate,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}