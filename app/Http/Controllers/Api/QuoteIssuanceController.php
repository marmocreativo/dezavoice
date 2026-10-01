<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IssueQuoteRequest;
use App\Models\Membership;
use App\Models\SalesProspect;
use App\Services\QuoteIssuanceService;
use RuntimeException;

class QuoteIssuanceController extends Controller
{
    public function __construct(
        private readonly QuoteIssuanceService $service,
    ) {}

    public function store(IssueQuoteRequest $request, SalesProspect $prospect)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        try {
            $result = $this->service->issue($actor, $prospect, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function resend(\App\Models\Opportunity $opportunity)
    {
        try {
            $this->service->resendPaymentLink($opportunity);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Correo reenviado.']);
    }

    public function checkoutUrl(\App\Models\Opportunity $opportunity)
    {
        $subscription = \App\Models\Subscription::where('opportunity_id', $opportunity->id)->latest()->first();

        if (! $subscription || ! $subscription->stripe_checkout_url) {
            return response()->json(['message' => 'No hay enlace de pago disponible.'], 404);
        }

        return response()->json(['data' => [
            'checkout_url' => $subscription->stripe_checkout_url,
            'status' => $subscription->status,
        ]]);
    }
}