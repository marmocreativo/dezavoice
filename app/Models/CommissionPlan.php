<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'market_id',
    'role',
    'name',
    'version',
    'effective_from',
    'effective_to',
])]
class CommissionPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CommissionPlan $plan) {
            if (empty($plan->uuid)) {
                $plan->uuid = (string) Str::uuid();
            }
        });
    }

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function rules()
    {
        return $this->hasMany(CommissionRule::class);
    }

    /**
     * El plan vigente para un mercado y rol en una fecha dada.
     * Si effective_to es null, el plan sigue vigente indefinidamente.
     */
    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }
}