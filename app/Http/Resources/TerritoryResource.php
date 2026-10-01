<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerritoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $won = $this->prospects_won_count ?? 0;
        $lost = $this->prospects_lost_count ?? 0;
        $pending = $this->prospects_pending_count ?? 0;
        $total = $won + $lost + $pending;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'active_sellers_count' => $this->active_sellers_count ?? 0,
            'prospects_total' => $total,
            'prospects_won_count' => $won,
            'prospects_lost_count' => $lost,
            'prospects_pending_count' => $pending,
            'prospects_won_percent' => $total > 0 ? round($won / $total * 100, 1) : 0,
            'prospects_lost_percent' => $total > 0 ? round($lost / $total * 100, 1) : 0,
            'prospects_pending_percent' => $total > 0 ? round($pending / $total * 100, 1) : 0,
            'market' => [
                'uuid' => $this->market->uuid,
                'name' => $this->market->name,
            ],
            'manager' => $this->whenLoaded('assignedManager', fn () => [
                'uuid' => $this->assignedManager?->uuid,
                'name' => $this->assignedManager?->user?->name,
            ]),
        ];
    }
}