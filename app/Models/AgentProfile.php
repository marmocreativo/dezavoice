<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'nombre_negocio',
    'direccion',
    'horario',
    'tiempo_preparacion',
    'formas_de_pago',
    'menu',
    'instrucciones_adicionales',
])]
class AgentProfile extends Model
{
    protected static function booted(): void
    {
        static::creating(function (AgentProfile $profile) {
            if (empty($profile->uuid)) {
                $profile->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Variables dinámicas que recibe el agente de Retell. Todas son texto y siempre se envían todas:
     * Retell deja visible el "{{variable}}" crudo cuando una variable no llega.
     *
     * @return array<string, string>
     */
    public static function variablesFor(Organization $organization): array
    {
        $profile = static::where('organization_id', $organization->id)->first();

        $pick = fn (?string $value, string $fallback): string => filled($value) ? trim($value) : $fallback;

        return [
            'nombre_negocio' => $pick($profile?->nombre_negocio, (string) $organization->name),
            'direccion' => $pick($profile?->direccion, 'No especificada'),
            'horario' => $pick($profile?->horario, 'No especificado'),
            'tiempo_preparacion' => $pick($profile?->tiempo_preparacion, 'No especificado'),
            'formas_de_pago' => $pick($profile?->formas_de_pago, 'No especificadas'),
            'menu' => $pick($profile?->menu, 'El menú de este negocio todavía no está configurado.'),
            'instrucciones_adicionales' => $pick($profile?->instrucciones_adicionales, 'Ninguna.'),
        ];
    }
}