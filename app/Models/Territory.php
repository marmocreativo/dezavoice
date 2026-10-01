<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'market_id',
    'name',
    'geo_reference',
    'assigned_manager_membership_id',
])]
class Territory extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Territory $territory) {
            if (empty($territory->uuid)) {
                $territory->uuid = (string) Str::uuid();
            }
        });
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function assignedManager()
    {
        return $this->belongsTo(Membership::class, 'assigned_manager_membership_id');
    }

    public function salesTeams()
    {
        return $this->hasMany(SalesTeam::class);
    }

    public function supervisors()
    {
        return $this->hasMany(Membership::class)
            ->where('role', 'supervisor')
            ->where('status', 'active');
    }
}