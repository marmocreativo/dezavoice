<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'retell_call_id',
    'organization_id',
    'subscription_id',
    'plan_id',
    'canal',
    'status',
    'started_at',
    'ended_at',
    'duration_ms',
    'minutos_consumidos',
    'minutos_aplicados_at',
    'disconnection_reason',
    'from_number',
    'to_number',
])]
class RetellCall extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutos_aplicados_at' => 'datetime',
            'minutos_consumidos' => 'decimal:2',
            'duration_ms' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RetellCall $call) {
            if (empty($call->uuid)) {
                $call->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /** El mensaje (pedido o resumen) generado por esta llamada. */
    public function message()
    {
        return $this->hasOne(ClientMessage::class, 'retell_call_id', 'retell_call_id');
    }
}