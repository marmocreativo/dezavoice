<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'outcome' => $this->outcome,
            'notes' => $this->notes,
            'prospect' => [
                'uuid' => $this->prospect->uuid,
                'business_name' => $this->prospect->business_name,
            ],
        ];
    }
}