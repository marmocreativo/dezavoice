<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'market_id',
    'territory_id',
    'manager_membership_id',
    'name',
    'is_active',
])]
class SalesTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalesTeam $salesTeam) {
            if (empty($salesTeam->uuid)) {
                $salesTeam->uuid = (string) Str::uuid();
            }
        });
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function territory()
    {
        return $this->belongsTo(Territory::class);
    }

    public function manager()
    {
        return $this->belongsTo(Membership::class, 'manager_membership_id');
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function supervisor()
    {
        return $this->hasOne(Membership::class)->where('role', 'supervisor');
    }
}