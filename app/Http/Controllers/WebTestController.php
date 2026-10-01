<?php

namespace App\Http\Controllers;

use App\Exceptions\WebTestException;
use App\Models\Organization;
use App\Models\RetellCall;
use App\Services\Retell\WebTestCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class WebTestController extends Controller
{
    public function __construct(private readonly WebTestCallService $calls) {}

    public function show(Organization $organization): View
    {
        $subscription = $this->calls->activeSubscription($organization);

        $blockReason = match (true) {
            ! $subscription => 'Este cliente no tiene una suscripción activa.',
            $this->calls->remainingMinutes($subscription) < 1 => 'No quedan minutos disponibles en el plan.',
            default => null,
        };

        $menuMissing = ! filled(\App\Models\AgentProfile::where('organization_id', $organization->id)->value('menu'));

        return view('web_test.show', compact('organization', 'subscription', 'blockReason', 'menuMissing'));
    }

    public function start(Organization $organization): JsonResponse
    {
        try {
            return response()->json($this->calls->start($organization));
        } catch (WebTestException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo iniciar la llamada. Intenta de nuevo.'], 502);
        }
    }

    public function status(RetellCall $retellCall): JsonResponse
    {
        $retellCall->load(['subscription', 'message']);

        $subscription = $retellCall->subscription;
        $limit = (int) $subscription->minutos_mensuales;
        $used = (float) $subscription->minutos_utilizados;
        $message = $retellCall->message;

        return response()->json([
            'status' => $retellCall->status,
            'minutos_consumidos' => $retellCall->minutos_consumidos !== null ? (float) $retellCall->minutos_consumidos : null,
            'pedido' => $message?->tipo === 'pedido' ? $message->mensaje : null,
            'uso' => [
                'usados' => $used,
                'limite' => $limit,
                'restantes' => max(0, round($limit - $used, 2)),
            ],
        ]);
    }
}