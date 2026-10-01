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
    'legal_name',
    'tax_id',
    'billing_email',
    'phone',
    'address_line1',
    'city',
    'state',
    'postal_code',
    'country',
    'type',
    'status',
])]
class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            if (empty($organization->uuid)) {
                $organization->uuid = (string) Str::uuid();
            }
        });
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }
}