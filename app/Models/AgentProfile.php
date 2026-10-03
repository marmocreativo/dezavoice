<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'sales_prospect_id',
    'nombre_negocio',
    'descripcion',
    'tipo_solicitud',
    'direccion',
    'horario',
    'tiempo_preparacion',
    'formas_de_pago',
    'menu',
    'instrucciones_adicionales',
])]
class AgentProfile extends Model
{
    /** Qué registra el agente en este negocio. Las claves son internas; solo cambia lo que se muestra. */
    public const REQUEST_TYPES = [
        'solicitud' => ['label' => 'Solicitud', 'plural' => 'Solicitudes', 'singular' => 'solicitud', 'new' => 'Nueva solicitud'],
        'pedido' => ['label' => 'Pedido', 'plural' => 'Pedidos', 'singular' => 'pedido', 'new' => 'Nuevo pedido'],
        'cita' => ['label' => 'Cita', 'plural' => 'Citas', 'singular' => 'cita', 'new' => 'Nueva cita'],
        'reserva' => ['label' => 'Reserva', 'plural' => 'Reservas', 'singular' => 'reserva', 'new' => 'Nueva reserva'],
        'cotizacion' => ['label' => 'Cotización', 'plural' => 'Cotizaciones', 'singular' => 'cotización', 'new' => 'Nueva cotización'],
        'recado' => ['label' => 'Recado', 'plural' => 'Recados', 'singular' => 'recado', 'new' => 'Nuevo recado'],
    ];

    /** Cómo se dice la moneda al hablar: [unidad, fracción]. */
    public const CURRENCIES = [
        'PEN' => ['soles', 'céntimos'],
        'MXN' => ['pesos', 'centavos'],
        'USD' => ['dólares', 'centavos'],
        'COP' => ['pesos', 'centavos'],
        'CLP' => ['pesos', 'centavos'],
        'ARS' => ['pesos', 'centavos'],
        'EUR' => ['euros', 'céntimos'],
    ];

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

    public function prospect()
    {
        return $this->belongsTo(SalesProspect::class, 'sales_prospect_id');
    }

    /** El prospecto del que nació esta organización (por su suscripción más reciente). */
    public static function prospectIdFor(Organization $organization): ?int
    {
        $id = Subscription::query()
            ->where('organization_id', $organization->id)
            ->with('opportunity:id,sales_prospect_id')
            ->orderByDesc('id')
            ->first()
            ?->opportunity
            ?->sales_prospect_id;

        return $id ? (int) $id : null;
    }

    /** Perfil existente de una organización: primero el de su prospecto, luego el ligado directo a ella. */
    public static function findFor(Organization $organization): ?self
    {
        $prospectId = static::prospectIdFor($organization);

        return ($prospectId ? static::where('sales_prospect_id', $prospectId)->first() : null)
            ?? static::where('organization_id', $organization->id)->first();
    }

    /** Para editar desde el panel: el existente o uno nuevo ligado a su prospecto. */
    public static function forOrganization(Organization $organization): self
    {
        if ($existing = static::findFor($organization)) {
            return $existing;
        }

        $prospectId = static::prospectIdFor($organization);

        return new static(array_filter([
            'organization_id' => $organization->id,
            'sales_prospect_id' => $prospectId,
        ]));
    }

    /** Para editar desde la PWA: el perfil del prospecto, adoptando uno antiguo ligado a su organización. */
    public static function forProspect(SalesProspect $prospect): self
    {
        if ($existing = static::where('sales_prospect_id', $prospect->id)->first()) {
            return $existing;
        }

        $organizationIds = Subscription::query()
            ->whereHas('opportunity', fn ($q) => $q->where('sales_prospect_id', $prospect->id))
            ->pluck('organization_id');

        $legacy = static::query()
            ->whereIn('organization_id', $organizationIds)
            ->whereNull('sales_prospect_id')
            ->first();

        if ($legacy) {
            $legacy->sales_prospect_id = $prospect->id; // se guarda al actualizar

            return $legacy;
        }

        return new static(['sales_prospect_id' => $prospect->id]);
    }

    /** Etiquetas de lo que registra el agente de un cliente (Pedido, Cita, Reserva…). */
    public static function requestLabelsFor(?Organization $organization): array
    {
        $type = $organization ? static::findFor($organization)?->tipo_solicitud : null;

        return self::REQUEST_TYPES[$type] ?? self::REQUEST_TYPES['solicitud'];
    }

    /** @return array{0: string, 1: string} */
    public static function currencyWords(?string $code): array
    {
        return self::CURRENCIES[strtoupper((string) $code)] ?? [$code ?: 'la moneda local', 'centavos'];
    }

    /**
     * Variables dinámicas que recibe el agente de Retell. Todas son texto y siempre se envían todas:
     * Retell deja visible el "{{variable}}" crudo cuando una variable no llega.
     *
     * @return array<string, string>
     */
    public static function variablesFor(Organization $organization): array
    {
        $profile = static::findFor($organization);

        $pick = fn (?string $value, string $fallback): string => filled($value) ? trim($value) : $fallback;

        [$currency, $fraction] = static::currencyWords(Market::find($organization->market_id)?->currency);

        $catalog = $pick($profile?->menu, 'El catálogo de productos o servicios de este negocio todavía no está configurado.');
        $attention = $pick($profile?->tiempo_preparacion, 'No especificado');

        return [
            'nombre_negocio' => $pick($profile?->nombre_negocio, (string) $organization->name),
            'descripcion_negocio' => $pick($profile?->descripcion, 'No especificada'),
            'tipo_solicitud' => (self::REQUEST_TYPES[$profile?->tipo_solicitud ?? 'solicitud'] ?? self::REQUEST_TYPES['solicitud'])['singular'],
            'direccion' => $pick($profile?->direccion, 'No especificada'),
            'horario' => $pick($profile?->horario, 'No especificado'),
            'tiempo_atencion' => $attention,
            'formas_de_pago' => $pick($profile?->formas_de_pago, 'No especificadas'),
            'catalogo' => $catalog,
            'instrucciones_adicionales' => $pick($profile?->instrucciones_adicionales, 'Ninguna.'),
            'moneda' => $currency,
            'fracciones' => $fraction,

            // Nombres anteriores: los agentes ya publicados siguen funcionando.
            'menu' => $catalog,
            'tiempo_preparacion' => $attention,
        ];
    }
}