<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\PaymentConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    private const HANDLED_EVENTS = [
        'checkout.session.completed',
        'invoice.payment_succeeded',
        'customer.subscription.deleted',
        'customer.subscription.updated',
    ];

    public function __construct(
        private readonly PaymentConfirmationService $paymentService,
    ) {}

    public function handle(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            Log::warning('Stripe webhook con firma inválida.', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Firma inválida.'], 400);
        }

        if (! in_array($event->type, self::HANDLED_EVENTS, true)) {
            WebhookEvent::recordFor('stripe', $event->id, $event->type, 'ignored');

            return response()->json(['message' => 'Evento ignorado.'], 200);
        }

        $object = $event->data->object;

        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($object),
                'invoice.payment_succeeded' => $this->handleInvoicePaid($object),
                'customer.subscription.deleted' => $this->handleSubscriptionEnded($object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            };
        } catch (\Throwable $e) {
            Log::error('Error procesando webhook de Stripe.', ['event_id' => $event->id, 'error' => $e->getMessage()]);

            WebhookEvent::recordFor('stripe', $event->id, $event->type, 'failed', $event->toArray(), $e->getMessage());

            return response()->json(['message' => $e->getMessage()], 422);
        }

        WebhookEvent::recordFor('stripe', $event->id, $event->type, 'processed', $event->toArray());

        return response()->json(['message' => 'Procesado.'], 200);
    }

    /**
     * La sesión de checkout se completó y Stripe ya creó la suscripción.
     * Aquí solo guardamos el stripe_subscription_id — el pago en sí
     * (inicial y cada mes) se confirma vía invoice.paid.
     */
    private function handleCheckoutCompleted(object $session): void
    {
        $opportunityUuid = $session->metadata->opportunity_uuid ?? null;

        if (! $opportunityUuid || ! $session->subscription) {
            throw new \RuntimeException('Falta opportunity_uuid o subscription en checkout.session.completed.');
        }

        $subscription = Subscription::whereHas('opportunity', fn ($q) => $q->where('uuid', $opportunityUuid))->first();

        if (! $subscription) {
            throw new \RuntimeException("No se encontró Subscription para opportunity {$opportunityUuid}.");
        }

        $subscription->update(['stripe_subscription_id' => $session->subscription]);
    }

    private function handleInvoicePaid(object $invoice): void
    {
        $stripeSubscriptionId = $invoice->parent->subscription_details->subscription ?? null;

        if (! $stripeSubscriptionId) {
            return; // factura suelta, no ligada a una suscripción — no nos interesa
        }

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first();

        // Puede llegar antes que checkout.session.completed (orden de entrega
        // no garantizado). Fallback: localizar por opportunity_uuid en el
        // metadata de la suscripción de Stripe, y autorreparar el vínculo.
        if (! $subscription) {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $stripeSubscription = $stripe->subscriptions->retrieve($stripeSubscriptionId);
            $opportunityUuid = $stripeSubscription->metadata->opportunity_uuid ?? null;

            if ($opportunityUuid) {
                $subscription = Subscription::whereHas('opportunity', fn ($q) => $q->where('uuid', $opportunityUuid))->first();

                if ($subscription && ! $subscription->stripe_subscription_id) {
                    $subscription->update(['stripe_subscription_id' => $stripeSubscriptionId]);
                }
            }
        }

        if (! $subscription) {
            throw new \RuntimeException("No se encontró Subscription para stripe_subscription_id {$stripeSubscriptionId}.");
        }

        $isFirstInvoice = $invoice->billing_reason === 'subscription_create';

        $this->paymentService->confirmInvoice(
            subscription: $subscription,
            externalId: $invoice->id,
            idempotencyKey: $invoice->id,
            amountCents: $invoice->amount_paid,
            currency: strtoupper($invoice->currency),
            isFirstInvoice: $isFirstInvoice,
            rawPayload: (array) $invoice,
        );
    }

    private function handleSubscriptionEnded(object $stripeSubscription): void
    {
        Subscription::where('stripe_subscription_id', $stripeSubscription->id)
            ->update(['status' => 'cancelled', 'canceled_at' => now()]);
    }

    private function handleSubscriptionUpdated(object $stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
        ]);
    }
}