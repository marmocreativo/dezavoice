<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'entry_type' => $this->entry_type,
            'status' => $this->status,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}