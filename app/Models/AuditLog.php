<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'actor_membership_id',
    'action',
    'subject_type',
    'subject_id',
    'before',
    'after',
])]
class AuditLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'audit_log';

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            if (empty($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }
        });

        static::updating(function () {
            throw new \RuntimeException('Una entrada de auditoría no se puede modificar.');
        });
    }

    public function actor()
    {
        return $this->belongsTo(Membership::class, 'actor_membership_id');
    }

    public static function record(?Membership $actor, string $action, $subject, ?array $before = null, ?array $after = null): self
    {
        return static::create([
            'actor_membership_id' => $actor?->id,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'before' => $before,
            'after' => $after,
        ]);
    }
}