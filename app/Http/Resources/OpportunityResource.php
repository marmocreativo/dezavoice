<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'expected_amount_cents' => $this->expected_amount_cents,
            'currency' => $this->currency,
            'stage' => $this->stage,
            'probability' => $this->probability,
            'quotes' => QuoteResource::collection($this->whenLoaded('quotes')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}