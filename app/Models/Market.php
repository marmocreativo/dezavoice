<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'code',
    'name',
    'currency',
    'timezone',
    'is_active',
    'tax_name',
    'tax_rate',
])]
class Market extends Model
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
        static::creating(function (Market $market) {
            if (empty($market->uuid)) {
                $market->uuid = (string) Str::uuid();
            }
        });
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    public function salesTeams()
    {
        return $this->hasMany(SalesTeam::class);
    }

    public function territories()
    {
        return $this->hasMany(Territory::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }
}