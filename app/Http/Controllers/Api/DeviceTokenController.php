<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterDeviceRequest;
use App\Models\DeviceToken;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(RegisterDeviceRequest $request)
    {
        // Un dispositivo (endpoint) solo recibe las notificaciones de la última persona que lo registró.
        DeviceToken::where('endpoint', $request->validated('endpoint'))
            ->where('user_id', '!=', $request->user()->id)
            ->delete();

        $device = DeviceToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $request->validated('endpoint')],
            [
                'p256dh' => $request->validated('keys.p256dh'),
                'auth' => $request->validated('keys.auth'),
                'platform' => $request->validated('platform'),
                'last_used_at' => now(),
            ],
        );

        return response()->json(['data' => ['uuid' => $device->uuid]], 201);
    }

    /** Este dispositivo deja de recibir notificaciones de la cuenta actual. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return response()->json(['data' => ['removed' => true]]);
    }

    /** Envía una notificación de prueba a los dispositivos del usuario. */
    public function test(Request $request, WebPushService $push): JsonResponse
    {
        if (! $push->isConfigured()) {
            return response()->json(['message' => 'Las notificaciones push no están configuradas en el servidor.'], 503);
        }

        $sent = $push->sendToUser($request->user()->id, [
            'title' => 'Notificación de prueba',
            'body' => 'Si ves esto, las notificaciones de DEZA Voice funcionan en este dispositivo.',
            'url' => '/',
            'tag' => 'push-test',
        ]);

        if ($sent === 0) {
            return response()->json(['message' => 'No se pudo entregar la prueba. Activa las notificaciones en este dispositivo e intenta de nuevo.'], 422);
        }

        return response()->json(['data' => ['sent' => $sent]]);
    }

    /** Clave pública VAPID que la PWA necesita para suscribirse. */
    public function publicKey(): JsonResponse
    {
        $key = config('services.webpush.public_key');

        if (blank($key)) {
            return response()->json(['message' => 'Las notificaciones push no están configuradas.'], 503);
        }

        return response()->json(['data' => ['public_key' => $key]]);
    }
}