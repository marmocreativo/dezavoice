<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'name',
    'address',
    'lat',
    'lng',
    'timezone',
])]
class Location extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Location $location) {
            if (empty($location->uuid)) {
                $location->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function resolvedTimezone(): string
    {
        return $this->timezone ?? $this->organization->market->timezone;
    }
}