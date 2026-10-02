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

    /** Función personalizada `registrar_pedido`, llamada por el agente al confirmar el pedido. */
    public function registrarPedido(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $callId = $payload['call']['call_id'] ?? null;

        $call = $callId ? RetellCall::where('retell_call_id', $callId)->first() : null;

        if (! $call) {
            return response()->json(['ok' => false, 'message' => 'Llamada desconocida: no se registró el pedido.'], 404);
        }

        if ($call->status === 'ended') {
            return response()->json(['ok' => false, 'message' => 'La llamada ya terminó.'], 409);
        }

        $validator = Validator::make($payload['args'] ?? [], [
            'resumen_pedido' => ['required', 'string', 'max:3000'],
            'total_soles' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => 'Faltan datos del pedido: '.$validator->errors()->first()], 422);
        }

        $args = $validator->validated();

        $message = $this->calls->registerOrder($call, $args['resumen_pedido'], isset($args['total_soles']) ? (float) $args['total_soles'] : null);

        // Solo la primera vez: un reintento de Retell actualiza el pedido pero no repite el aviso.
        // Se envía después de responderle a Retell, para que la función no espere al servicio de push.
        if ($message->wasRecentlyCreated) {
            app()->terminating(fn () => app(OrderNotificationService::class)->notifyNewOrder($message));
        }

        // Sin número de pedido a propósito: el prompt prohíbe inventarlos.
        return response()->json(['ok' => true, 'message' => 'Pedido de demostración registrado.']);
    }
}