<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'sales_prospect_id',
    'from_status',
    'to_status',
    'changed_by_membership_id',
    'note',
    'changed_at',
])]
class ProspectStatusHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'prospect_status_history';

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProspectStatusHistory $history) {
            if (empty($history->uuid)) {
                $history->uuid = (string) Str::uuid();
            }
            if (empty($history->changed_at)) {
                $history->changed_at = now();
            }
        });
    }

    public function prospect()
    {
        return $this->belongsTo(SalesProspect::class, 'sales_prospect_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(Membership::class, 'changed_by_membership_id');
    }
}