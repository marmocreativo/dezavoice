<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'membership_id',
    'sale_id',
    'payment_id',
    'commission_rule_id',
    'entry_type',
    'status',
    'amount_cents',
    'currency',
    'calculation_snapshot',
    'reverses_ledger_id',
    'note',
    'created_by_membership_id',
])]
class CommissionLedger extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'commission_ledger';

    protected function casts(): array
    {
        return [
            'calculation_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CommissionLedger $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }
        });

        static::updating(function () {
            throw new \RuntimeException('Un asiento de comisión no se puede modificar. Cree un nuevo asiento con reverses_ledger_id.');
        });
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function commissionRule()
    {
        return $this->belongsTo(CommissionRule::class);
    }

    public function reverses()
    {
        return $this->belongsTo(CommissionLedger::class, 'reverses_ledger_id');
    }

    public function reversedBy()
    {
        return $this->hasOne(CommissionLedger::class, 'reverses_ledger_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Membership::class, 'created_by_membership_id');
    }

    /** El pago activo en el que ya se incluyó este asiento (null si sigue sin pagar). */
    public function payoutItem()
    {
        return $this->hasOne(CommissionPayoutEntry::class, 'commission_ledger_id');
    }

    public function scopeForMembership($query, int $membershipId)
    {
        return $query->where('membership_id', $membershipId);
    }
}