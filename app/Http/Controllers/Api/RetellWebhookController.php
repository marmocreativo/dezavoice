<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RetellCall;
use App\Models\WebhookEvent;
use App\Services\Retell\RetellCallService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class RetellWebhookController extends Controller
{
    public function __construct(private readonly RetellCallService $calls) {}

    public function handle(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        $event = (string) $request->input('event');
        $data = (array) $request->input('call', []);
        $callId = $data['call_id'] ?? null;

        $call = $callId ? RetellCall::where('retell_call_id', $callId)->first() : null;

        // Llamadas de otros agentes de la cuenta: no son nuestras, se acusa recibo y se ignoran.
        if (! $call) {
            return response()->noContent();
        }

        try {
            match ($event) {
                'call_started' => $this->calls->markStarted($call, $data['start_timestamp'] ?? null),
                'call_ended' => $this->calls->markEnded($call, $data),
                default => null,
            };

            $this->log($event, $callId, 'processed', $request->all());
        } catch (\Throwable $e) {
            report($e);
            $this->log($event, $callId, 'failed', $request->all(), $e->getMessage());

            // 5xx: Retell reintenta.
            return response()->json(['message' => 'Error procesando el evento.'], 500);
        }

        return response()->noContent();
    }

    private function log(string $event, string $callId, string $status, array $payload, ?string $note = null): void
    {
        try {
            WebhookEvent::recordFor(
                'retell',
                "{$event}:{$callId}",
                $event,
                $status,
                Arr::except($payload, ['call.transcript_object', 'call.transcript_with_tool_calls']),
                $note,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}