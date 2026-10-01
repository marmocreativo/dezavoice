<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'role',
    'market_id',
    'organization_id',
    'location_id',
    'sales_team_id',
    'territory_id',
    'scope',
    'codigo',
    'status',
    'started_at',
    'ended_at',
])]
class Membership extends Model
{
    use HasFactory, SoftDeletes;

    public const ROLES_WITH_CODE = ['seller', 'supervisor', 'manager'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Membership $membership) {
            if (empty($membership->uuid)) {
                $membership->uuid = (string) Str::uuid();
            }

            if (empty($membership->started_at)) {
                $membership->started_at = now();
            }

            if (empty($membership->codigo) && in_array($membership->role, self::ROLES_WITH_CODE, true)) {
                do {
                    $codigo = strtoupper(Str::random(3));
                } while (static::where('codigo', $codigo)->exists());

                $membership->codigo = $codigo;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function salesTeam()
    {
        return $this->belongsTo(SalesTeam::class);
    }

    public function territory()
    {
        return $this->belongsTo(Territory::class);
    }

    public function reportsTo()
    {
        return $this->hasOne(ReportingLine::class, 'member_membership_id')->active();
    }

    public function directReports()
    {
        return $this->hasMany(ReportingLine::class, 'manager_membership_id')->active();
    }

    public function commissionLedgerEntries()
    {
        return $this->hasMany(CommissionLedger::class);
    }

    public function bonusProgress()
    {
        return $this->hasMany(BonusProgress::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'seller_membership_id');
    }

    public function prospects()
    {
        return $this->hasMany(SalesProspect::class, 'owner_membership_id');
    }

    /**
     * Todas las membresías que reportan hacia esta, directa o indirectamente,
     * recorriendo reporting_lines activas. Incluye la propia.
     */
    /**
     * La cadena de esta membresía hacia arriba (ella misma, su supervisor
     * directo, el de este, etc.) siguiendo reporting_lines activas.
     */
    public function ancestorChain(): array
    {
        $chain = [$this];
        $current = $this;

        while ($line = $current->reportsTo) {
            $manager = $line->manager;

            if (! $manager) {
                break;
            }

            $chain[] = $manager;
            $current = $manager;
        }

        return $chain;
    }

    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while (! empty($frontier)) {
            $next = ReportingLine::query()
                ->active()
                ->whereIn('manager_membership_id', $frontier)
                ->pluck('member_membership_id')
                ->diff($ids)
                ->values()
                ->all();

            if (empty($next)) {
                break;
            }

            $ids = array_merge($ids, $next);
            $frontier = $next;
        }

        return $ids;
    }

    /**
     * Filtra un query de Membership a lo que $actor puede ver,
     * según la matriz de acceso del blueprint 9.3.
     */
    public function scopeAccessibleBy($query, Membership $actor)
    {
        return match ($actor->role) {
            'deza_admin' => $query,
            'manager' => $query->where('market_id', $actor->market_id),
            'supervisor', 'seller' => $query->whereIn('id', $actor->descendantIds()),
            default => $query->whereKey($actor->id),
        };
    }

    /**
     * IDs de membresía que $actor puede ver, según la matriz de acceso
     * del blueprint 9.3. Devuelve null para "sin restricción" (deza_admin),
     * en vez de una lista completa — evita un pluck() costoso en producción.
     */
    public static function visibleIdsFor(Membership $actor): ?array
    {
        return match ($actor->role) {
            'deza_admin' => null,
            'manager' => static::where('market_id', $actor->market_id)->pluck('id')->all(),
            'supervisor', 'seller' => $actor->descendantIds(),
            default => [$actor->id],
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}