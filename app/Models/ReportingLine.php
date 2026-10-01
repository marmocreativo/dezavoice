<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'member_membership_id',
    'manager_membership_id',
    'started_at',
    'ended_at',
])]
class ReportingLine extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReportingLine $line) {
            if (empty($line->uuid)) {
                $line->uuid = (string) Str::uuid();
            }
            if (empty($line->started_at)) {
                $line->started_at = now();
            }
        });
    }

    public function member()
    {
        return $this->belongsTo(Membership::class, 'member_membership_id');
    }

    public function manager()
    {
        return $this->belongsTo(Membership::class, 'manager_membership_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }
}