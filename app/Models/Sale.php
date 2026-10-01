<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'opportunity_id',
    'seller_membership_id',
    'payment_id',
    'amount_cents',
    'currency',
    'validated_at',
    'status',
])]
class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            if (empty($sale->uuid)) {
                $sale->uuid = (string) Str::uuid();
            }
        });
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function seller()
    {
        return $this->belongsTo(Membership::class, 'seller_membership_id');
    }

    public function activationCase()
    {
        return $this->hasOne(ActivationCase::class);
    }
}