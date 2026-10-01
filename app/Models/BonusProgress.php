<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'membership_id',
    'commission_rule_id',
    'period',
    'current_value',
    'target_value',
    'is_met',
    'computed_at',
])]
class BonusProgress extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_met' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BonusProgress $progress) {
            if (empty($progress->uuid)) {
                $progress->uuid = (string) Str::uuid();
            }
        });
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function commissionRule()
    {
        return $this->belongsTo(CommissionRule::class);
    }
}