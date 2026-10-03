<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuPhotoResource;
use App\Models\AuditLog;
use App\Models\MenuPhoto;
use App\Models\SalesProspect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MenuPhotoController extends Controller
{
    private const MAX_PHOTOS = 20;

    public function store(Request $request, SalesProspect $prospect): JsonResponse
    {
        $actor = $request->attributes->get('activeMembership');
        $this->authorize('update', [$prospect, $actor]);

        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:255'],
        ], [
            'photo.required' => 'Selecciona una foto.',
            'photo.image' => 'El archivo debe ser una imagen.',
            'photo.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'photo.max' => 'La foto no puede pesar más de 10 MB.',
        ]);

        if (MenuPhoto::where('sales_prospect_id', $prospect->id)->count() >= self::MAX_PHOTOS) {
            return response()->json(['message' => 'Ya hay '.self::MAX_PHOTOS.' fotos de menú. Elimina alguna antes de subir otra.'], 422);
        }

        $file = $request->file('photo');

        // Se leen antes de guardar: después de moverlo, el archivo temporal ya no existe.
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $size = (int) $file->getSize();
        $original = Str::limit($file->getClientOriginalName(), 200, '');
        $extension = $file->guessExtension() ?: 'jpg';

        $path = $file->storeAs("menu-photos/{$prospect->uuid}", Str::uuid().'.'.$extension, 'local');

        $photo = MenuPhoto::create([
            'sales_prospect_id' => $prospect->id,
            'path' => $path,
            'original_name' => $original,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'caption' => $request->input('caption'),
            'uploaded_by_membership_id' => $actor->id,
        ]);

        AuditLog::record(
            actor: $actor,
            action: 'menu_photo.uploaded',
            subject: $prospect,
            before: [],
            after: ['photo' => $photo->uuid],
        );

        return (new MenuPhotoResource($photo))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, MenuPhoto $photo): JsonResponse
    {
        $actor = $request->attributes->get('activeMembership');
        $this->authorize('update', [$photo->prospect, $actor]);

        Storage::disk('local')->delete($photo->path);
        $photo->delete();

        AuditLog::record(
            actor: $actor,
            action: 'menu_photo.deleted',
            subject: $photo->prospect,
            before: ['photo' => $photo->uuid],
            after: [],
        );

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** Sirve la imagen. Va protegida por la firma de la URL, no por sesión: los <img> no mandan token. */
    public function file(MenuPhoto $photo): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Type' => $photo->mime_type,
        ]);
    }
}