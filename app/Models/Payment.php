<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'invoice_id',
    'opportunity_id',
    'provider',
    'external_id',
    'idempotency_key',
    'amount_cents',
    'currency',
    'status',
    'confirmed_at',
    'raw_payload',
])]
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function sale()
    {
        return $this->hasOne(Sale::class);
    }
}