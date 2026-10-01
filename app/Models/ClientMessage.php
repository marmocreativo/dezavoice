<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'subscription_id',
    'plan_id',
    'fecha',
    'minutos_consumidos',
    'mensaje',
    'retell_call_id',
    'canal',
    'tipo',
])]
class ClientMessage extends Model
{
    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'minutos_consumidos' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ClientMessage $message) {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
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
}