<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'membership_id',
    'currency',
    'total_cents',
    'entries_count',
    'paid_at',
    'method',
    'reference',
    'note',
    'entries_snapshot',
    'created_by_membership_id',
    'voided_at',
    'voided_by_membership_id',
    'void_reason',
])]
class CommissionPayout extends Model
{
    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'voided_at' => 'datetime',
            'entries_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CommissionPayout $payout) {
            if (empty($payout->uuid)) {
                $payout->uuid = (string) Str::uuid();
            }
        });
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class)->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(Membership::class, 'created_by_membership_id')->withTrashed();
    }

    public function entries()
    {
        return $this->hasMany(CommissionPayoutEntry::class);
    }
}