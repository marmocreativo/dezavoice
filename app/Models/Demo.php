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
    'scheduled_at',
    'completed_at',
    'outcome',
    'notes',
])]
class Demo extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Demo $demo) {
            if (empty($demo->uuid)) {
                $demo->uuid = (string) Str::uuid();
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