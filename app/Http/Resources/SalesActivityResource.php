<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'notes' => $this->notes,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'checkin_lat' => $this->checkin_lat,
            'checkin_lng' => $this->checkin_lng,
        ];
    }
}