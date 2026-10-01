<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'provider',
    'external_event_id',
    'event_type',
    'status',
    'payload',
    'note',
    'processed_at',
])]
class WebhookEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WebhookEvent $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    public static function recordFor(string $provider, string $externalEventId, string $eventType, string $status, ?array $payload = null, ?string $note = null): self
    {
        return static::updateOrCreate(
            ['provider' => $provider, 'external_event_id' => $externalEventId],
            [
                'event_type' => $eventType,
                'status' => $status,
                'payload' => $payload,
                'note' => $note,
                'processed_at' => now(),
            ],
        );
    }
}