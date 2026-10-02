<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'fecha' => $this->fecha->toIso8601String(),
            'tipo' => $this->tipo,
            'canal' => $this->canal,
            'mensaje' => $this->mensaje,
            'minutos_consumidos' => (float) $this->minutos_consumidos,
            'plan' => $this->plan?->name,
        ];
    }
}