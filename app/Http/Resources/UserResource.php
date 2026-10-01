<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path) : null,
            'must_change_password' => $this->must_change_password,
        ];
    }
}