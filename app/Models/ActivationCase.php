<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'sale_id',
    'organization_id',
    'status',
    'went_live_at',
])]
class ActivationCase extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'went_live_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ActivationCase $case) {
            if (empty($case->uuid)) {
                $case->uuid = (string) Str::uuid();
            }
        });
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}