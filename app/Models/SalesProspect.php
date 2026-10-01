<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'owner_membership_id',
    'market_id',
    'business_name',
    'giro',
    'address',
    'lat',
    'lng',
    'status',
    'lost_reason',
    'next_action_at',
])]
class SalesProspect extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'nuevo', 'calificado', 'demo_programada', 'demo_realizada',
        'propuesta_enviada', 'pago_pendiente', 'venta_ganada',
        'activacion', 'cliente_activo',
        'no_calificado', 'perdido', 'pausado', 'cancelado', 'reembolsado',
    ];

    protected function casts(): array
    {
        return [
            'next_action_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalesProspect $prospect) {
            if (empty($prospect->uuid)) {
                $prospect->uuid = (string) Str::uuid();
            }
        });
    }

    public function owner()
    {
        return $this->belongsTo(Membership::class, 'owner_membership_id');
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function contacts()
    {
        return $this->hasMany(SalesContact::class);
    }

    public function activities()
    {
        return $this->hasMany(SalesActivity::class);
    }

    public function demos()
    {
        return $this->hasMany(Demo::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(ProspectStatusHistory::class);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('next_action_at')->where('next_action_at', '<', now());
    }
}