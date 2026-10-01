<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'commission_plan_id',
    'plan_id',
    'activation_amount_cents',
    'recurring_amount_cents',
    'recurring_percentage',
    'duration_months',
    'requires_personal_sale',
    'individual_goal',
    'team_goal',
    'team_size_requirement',
    'qualifying_members_required',
    'payment_condition',
    'waiting_period_days',
    'reversal_window_days',
    'bonus_definition',
])]
class CommissionRule extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'requires_personal_sale' => 'boolean',
            'bonus_definition' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CommissionRule $rule) {
            if (empty($rule->uuid)) {
                $rule->uuid = (string) Str::uuid();
            }
        });
    }

    public function commissionPlan()
    {
        return $this->belongsTo(CommissionPlan::class);
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(CommissionLedger::class);
    }
}