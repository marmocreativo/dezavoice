<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable([
    'sales_prospect_id',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'caption',
    'uploaded_by_membership_id',
])]
class MenuPhoto extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (MenuPhoto $photo) {
            if (empty($photo->uuid)) {
                $photo->uuid = (string) Str::uuid();
            }
        });
    }

    public function prospect()
    {
        return $this->belongsTo(SalesProspect::class, 'sales_prospect_id');
    }

    /** La foto es privada: solo se sirve con esta URL firmada, que caduca. */
    public function signedUrl(): string
    {
        return URL::temporarySignedRoute('menu-photos.file', now()->addHours(6), ['photo' => $this->uuid]);
    }
}