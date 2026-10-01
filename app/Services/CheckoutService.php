<?php

namespace App\Services;

use App\Models\Opportunity;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class CheckoutService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createForOpportunity(
        Opportunity $opportunity,
        int $monthlyCents,
        int $setupFeeCents,
        string $currency,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
    ): Session {
        $quote = $opportunity->quotes()->latest('version')->first();

        if (! $quote) {
            throw new RuntimeException('La oportunidad no tiene ninguna cotización generada.');
        }

        $prospect = $opportunity->prospect;

        $lineItems = [
            [
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $monthlyCents,
                    'recurring' => ['interval' => 'month'],
                    'product_data' => [
                        'name' => "DEZA Voice — {$prospect->business_name} (mensualidad)",
                    ],
                ],
                'quantity' => 1,
            ],
        ];

        if ($setupFeeCents > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $setupFeeCents,
                    'product_data' => [
                        'name' => "DEZA Voice — {$prospect->business_name} (configuración inicial)",
                    ],
                ],
                'quantity' => 1,
            ];
        }

        return $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'line_items' => $lineItems,
            'metadata' => [
                'opportunity_uuid' => $opportunity->uuid,
                'quote_uuid' => $quote->uuid,
            ],
            'subscription_data' => [
                'metadata' => [
                    'opportunity_uuid' => $opportunity->uuid,
                ],
            ],
            'success_url' => $successUrl ?? env('FRONTEND_URL', 'http://localhost:5173') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl ?? env('FRONTEND_URL', 'http://localhost:5173') . '/checkout/cancel',
        ]);
    }
}