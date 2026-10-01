<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'territory' => $this->whenLoaded('territory', fn () => $this->territory ? [
                'uuid' => $this->territory->uuid,
                'name' => $this->territory->name,
            ] : null),
            'supervisor' => $this->supervisor ? [
                'uuid' => $this->supervisor->uuid,
                'name' => $this->supervisor->user?->name,
            ] : null,
            'sellers_count' => $this->whenCounted('sellers'),
        ];
    }
}