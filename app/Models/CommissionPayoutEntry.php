<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['commission_payout_id', 'commission_ledger_id', 'amount_cents'])]
class CommissionPayoutEntry extends Model
{
    public function payout()
    {
        return $this->belongsTo(CommissionPayout::class, 'commission_payout_id');
    }

    public function ledger()
    {
        return $this->belongsTo(CommissionLedger::class, 'commission_ledger_id');
    }
}