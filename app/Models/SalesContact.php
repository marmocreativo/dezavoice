<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'sales_prospect_id',
    'name',
    'phone',
    'email',
    'role',
    'is_primary',
])]
class SalesContact extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalesContact $contact) {
            if (empty($contact->uuid)) {
                $contact->uuid = (string) Str::uuid();
            }
        });
    }

    public function prospect()
    {
        return $this->belongsTo(SalesProspect::class, 'sales_prospect_id');
    }
}