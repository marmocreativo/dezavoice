<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'phone_number',
    'label',
    'forwarded_from',
    'retell_agent_id',
    'is_active',
    'notes',
])]
class ClientPhoneNumber extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (ClientPhoneNumber $number) {
            if (empty($number->uuid)) {
                $number->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}