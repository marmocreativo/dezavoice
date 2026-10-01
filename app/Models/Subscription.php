<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'opportunity_id',
    'stripe_subscription_id',
    'stripe_checkout_url',
    'plan_id',
    'status',
    'started_at',
    'current_period_end',
    'canceled_at',
])]
class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription) {
            if (empty($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}