<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckoutRequest;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\Opportunity;
use App\Services\CheckoutService;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
    ) {}

    public function store(CreateCheckoutRequest $request, Opportunity $opportunity)
    {
        try {
            $session = $this->checkoutService->createForOpportunity(
                opportunity: $opportunity,
                successUrl: $request->validated('success_url'),
                cancelUrl: $request->validated('cancel_url'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CheckoutSessionResource($session);
    }
}