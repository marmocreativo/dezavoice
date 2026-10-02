<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Retell\InboundCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetellInboundController extends Controller
{
    public function __construct(private readonly InboundCallService $inbound) {}

    public function handle(Request $request): JsonResponse
    {
        $data = (array) $request->input('call_inbound', []);

        return response()->json([
            'call_inbound' => $this->inbound->handle(
                $data['call_id'] ?? null,
                $data['from_number'] ?? null,
                $data['to_number'] ?? null,
            ),
        ]);
    }
}