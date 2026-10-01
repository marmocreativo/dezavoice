<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'sales_prospect_id',
    'membership_id',
    'type',
    'notes',
    'occurred_at',
    'checkin_lat',
    'checkin_lng',
])]
class SalesActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalesActivity $activity) {
            if (empty($activity->uuid)) {
                $activity->uuid = (string) Str::uuid();
            }
            if (empty($activity->occurred_at)) {
                $activity->occurred_at = now();
            }
        });
    }

    public function prospect()
    {
        return $this->belongsTo(SalesProspect::class, 'sales_prospect_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}