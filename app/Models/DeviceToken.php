<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'endpoint',
    'p256dh',
    'auth',
    'platform',
    'last_used_at',
])]
class DeviceToken extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DeviceToken $token) {
            if (empty($token->uuid)) {
                $token->uuid = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}