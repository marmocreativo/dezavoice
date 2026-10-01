<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'membership_id',
    'type',
    'title',
    'body',
    'data',
    'read_at',
])]
class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Notification $notification) {
            if (empty($notification->uuid)) {
                $notification->uuid = (string) Str::uuid();
            }
        });
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }
}