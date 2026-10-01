<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesProspectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestOpportunity = $this->opportunities()->latest()->first();

        return [
            'uuid' => $this->uuid,
            'business_name' => $this->business_name,
            'giro' => $this->giro,
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => $this->status,
            'lost_reason' => $this->lost_reason,
            'next_action_at' => $this->next_action_at?->toIso8601String(),
            'contacts' => SalesContactResource::collection($this->whenLoaded('contacts')),
            'opportunity_uuid' => $latestOpportunity?->uuid,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}