<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'subscription_id',
    'amount_cents',
    'currency',
    'status',
    'due_at',
    'paid_at',
])]
class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->uuid)) {
                $invoice->uuid = (string) Str::uuid();
            }
        });
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}