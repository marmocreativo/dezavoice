<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'version' => $this->version,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'valid_until' => $this->valid_until?->toDateString(),
            'sent_at' => $this->sent_at?->toIso8601String(),
        ];
    }
}