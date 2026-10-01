<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'market_id',
    'code',
    'name',
    'price_cents',
    'setup_fee_cents',
    'currency',
    'billing_period',
])]
class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->uuid)) {
                $plan->uuid = (string) Str::uuid();
            }
        });
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }
}