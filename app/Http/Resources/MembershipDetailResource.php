<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'role' => $this->role,
            'codigo' => $this->codigo,
            'status' => $this->status,
            'user' => [
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
        ];
    }
}