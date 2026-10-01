<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'sales_prospect_id',
    'plan_id',
    'expected_amount_cents',
    'currency',
    'stage',
    'probability',
])]
class Opportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Opportunity $opportunity) {
            if (empty($opportunity->uuid)) {
                $opportunity->uuid = (string) Str::uuid();
            }
        });
    }

    public function prospect()
    {
        return $this->belongsTo(SalesProspect::class, 'sales_prospect_id');
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function sale()
    {
        return $this->hasOne(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}