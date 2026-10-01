<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'checkout_url' => $this->resource->url,
            'session_id' => $this->resource->id,
        ];
    }
}