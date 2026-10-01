<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'opportunity_id',
    'version',
    'amount_cents',
    'currency',
    'valid_until',
    'pdf_path',
    'sent_at',
])]
class Quote extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            if (empty($quote->uuid)) {
                $quote->uuid = (string) Str::uuid();
            }

            if (empty($quote->version)) {
                $quote->version = static::where('opportunity_id', $quote->opportunity_id)->max('version') + 1;
            }
        });
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }
}