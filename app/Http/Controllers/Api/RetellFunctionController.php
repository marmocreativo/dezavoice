<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RetellCall;
use App\Services\OrderNotificationService;
use App\Services\Retell\RetellCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RetellFunctionController extends Controller
{
    public function __construct(private readonly RetellCallService $calls) {}

    /** Función personalizada: el agente registra lo que la persona pidió, agendó o dejó dicho. */
    public function registrarPedido(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $callId = $payload['call']['call_id'] ?? null;

        $call = $callId ? RetellCall::where('retell_call_id', $callId)->first() : null;

        if (! $call) {
            return response()->json(['ok' => false, 'message' => 'Llamada desconocida: no se registró la solicitud.'], 404);
        }

        if ($call->status === 'ended') {
            return response()->json(['ok' => false, 'message' => 'La llamada ya terminó.'], 409);
        }

        $args = $payload['args'] ?? [];

        $validator = Validator::make([
            'resumen' => $args['resumen'] ?? $args['resumen_pedido'] ?? null,
            'total' => $args['total'] ?? $args['total_soles'] ?? null,
            'contacto' => $args['contacto'] ?? null,
        ], [
            'resumen' => ['required', 'string', 'max:3000'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'contacto' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => 'Faltan datos: '.$validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        $message = $this->calls->registerOrder(
            $call,
            $data['resumen'],
            isset($data['total']) ? (float) $data['total'] : null,
            $data['contacto'] ?? null,
        );

        // Solo la primera vez: un reintento de Retell actualiza el registro pero no repite el aviso.
        // Se envía después de responderle a Retell, para que la función no espere al servicio de push.
        if ($message->wasRecentlyCreated) {
            app()->terminating(fn () => app(OrderNotificationService::class)->notifyNewOrder($message));
        }

        // Sin número de confirmación a propósito: el prompt prohíbe inventarlos.
        return response()->json(['ok' => true, 'message' => 'Solicitud registrada.']);
    }
}