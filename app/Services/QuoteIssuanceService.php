<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Membership;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\SalesProspect;
use App\Models\Subscription;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class QuoteIssuanceService
{
    public function __construct(
        private readonly ProspectStatusService $statusService,
        private readonly CheckoutService $checkoutService,
    ) {}

    public function issue(Membership $seller, SalesProspect $prospect, array $data): array
    {
        $plan = Plan::where('uuid', $data['plan_uuid'])->firstOrFail();
        $market = $plan->market;

        $taxRate = (float) ($market->tax_rate ?? 0);
        $monthlyCents = (int) round($plan->price_cents * (1 + $taxRate / 100));
        $setupFeeCents = (int) round($plan->setup_fee_cents * (1 + $taxRate / 100));
        $firstChargeCents = $monthlyCents + $setupFeeCents;

        return DB::transaction(function () use ($seller, $prospect, $plan, $market, $data, $monthlyCents, $setupFeeCents, $firstChargeCents) {
            $organization = Organization::create([
                'market_id' => $prospect->market_id,
                'name' => $data['organization_name'],
                'legal_name' => $data['legal_name'],
                'tax_id' => $data['tax_id'],
                'billing_email' => $data['billing_email'],
                'phone' => $data['phone'],
                'address_line1' => $data['address_line1'],
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'],
                'country' => $data['country'],
                'type' => 'client',
                'status' => 'pending',
            ]);

            Location::create([
                'organization_id' => $organization->id,
                'name' => $data['organization_name'],
                'address' => $data['address_line1'],
                'lat' => $prospect->lat,
                'lng' => $prospect->lng,
                'timezone' => $market->timezone,
            ]);

            $existingUser = User::where('email', $data['contact_email'])->first();
            $temporaryPassword = null;

            if ($existingUser) {
                $clientUser = $existingUser;
            } else {
                $temporaryPassword = Str::random(10);

                $clientUser = User::create([
                    'name' => $data['contact_name'],
                    'email' => $data['contact_email'],
                    'password' => $temporaryPassword,
                    'must_change_password' => true,
                ]);
            }

            Membership::create([
                'user_id' => $clientUser->id,
                'role' => 'client',
                'market_id' => $prospect->market_id,
                'organization_id' => $organization->id,
                'status' => 'active',
            ]);

            $opportunity = $prospect->opportunities()->create([
                'plan_id' => $plan->id,
                'expected_amount_cents' => $monthlyCents,
                'currency' => $plan->currency,
                'stage' => 'open',
            ]);

            // El monto de la cotización refleja el cobro inicial completo
            // (mensualidad + configuración, con impuesto ya incluido).
            $quote = $opportunity->quotes()->create([
                'amount_cents' => $firstChargeCents,
                'currency' => $plan->currency,
                'sent_at' => now(),
            ]);

            $subscription = Subscription::create([
                'organization_id' => $organization->id,
                'opportunity_id' => $opportunity->id,
                'plan_id' => $plan->id,
                'status' => 'pending_payment',
                'started_at' => now(),
            ]);

            if ($prospect->status === 'demo_realizada') {
                $this->statusService->transitionManually(
                    prospect: $prospect,
                    toStatus: 'propuesta_enviada',
                    actor: $seller,
                    note: "Cotización del plan {$plan->name} generada y enviada.",
                );
            }

            $checkoutSession = $this->checkoutService->createForOpportunity(
                opportunity: $opportunity,
                monthlyCents: $monthlyCents,
                setupFeeCents: $setupFeeCents,
                currency: $plan->currency,
            );

            $subscription->update(['stripe_checkout_url' => $checkoutSession->url]);

            $this->sendPaymentLinkEmail($data['contact_email'], $data['contact_name'], $plan, $firstChargeCents, $checkoutSession->url);

            if ($temporaryPassword) {
                $this->sendWelcomeEmail($data['contact_email'], $data['contact_name'], $temporaryPassword, $organization, $plan, $monthlyCents);
            }

            return [
                'organization_uuid' => $organization->uuid,
                'opportunity_uuid' => $opportunity->uuid,
                'quote_uuid' => $quote->uuid,
                'checkout_url' => $checkoutSession->url,
            ];
        });
    }

    /**
     * Reenvía el correo con el enlace de pago ya generado (no crea una
     * Subscription nueva en Stripe). Falla si aún no existe un checkout
     * previo o si la suscripción ya está activa/pagada.
     */
    public function resendPaymentLink(Opportunity $opportunity): void
    {
        $subscription = Subscription::where('opportunity_id', $opportunity->id)->latest()->first();

        if (! $subscription || ! $subscription->stripe_checkout_url) {
            throw new \RuntimeException('Esta oportunidad no tiene un enlace de pago generado todavía.');
        }

        if ($subscription->status === 'active') {
            throw new \RuntimeException('Esta suscripción ya está activa; no es necesario reenviar el pago.');
        }

        $quote = $opportunity->quotes()->latest('version')->first();
        $prospect = $opportunity->prospect;
        $organization = $subscription->organization;
        $contactMembership = Membership::where('organization_id', $organization->id)->where('role', 'client')->first();
        $contactUser = $contactMembership?->user;

        if (! $contactUser || ! $quote) {
            throw new \RuntimeException('No se encontró la información del contacto para reenviar el correo.');
        }

        $this->sendPaymentLinkEmail($contactUser->email, $contactUser->name, $opportunity->subscriptionPlan, $quote->amount_cents, $subscription->stripe_checkout_url);
    }

    private function sendPaymentLinkEmail(string $email, string $name, Plan $plan, int $firstChargeCents, string $checkoutUrl): void
    {
        $amount = number_format($firstChargeCents / 100, 2);

        $html = $this->emailLayout(
            title: 'Tu enlace de pago está listo',
            bodyHtml: "
                <p style=\"margin:0 0 16px;\">Hola {$this->e($name)},</p>
                <p style=\"margin:0 0 16px;\">Aquí está el enlace para completar el pago de tu plan <strong>{$this->e($plan->name)}</strong>.</p>
                <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:20px 0;background:#F4F6F8;border-radius:10px;\">
                    <tr>
                        <td style=\"padding:16px 20px;\">
                            <p style=\"margin:0;color:#6B7A87;font-size:13px;\">Primer cobro (mensualidad + configuración inicial)</p>
                            <p style=\"margin:4px 0 0;color:#091925;font-size:22px;font-weight:700;\">{$this->e($plan->currency)} {$amount}</p>
                        </td>
                    </tr>
                </table>
                <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:0 0 20px;\">
                    <tr>
                        <td style=\"border-radius:8px;background:#FF6A00;\">
                            <a href=\"{$this->e($checkoutUrl)}\" style=\"display:inline-block;padding:14px 28px;color:#FFFFFF;font-weight:600;font-size:15px;text-decoration:none;border-radius:8px;\">Completar pago</a>
                        </td>
                    </tr>
                </table>
                <p style=\"margin:0 0 8px;color:#6B7A87;font-size:13px;\">A partir del segundo mes se te cobrará automáticamente solo la mensualidad.</p>
            ",
        );

        Mail::html($html, function ($message) use ($email, $name) {
            $message->to($email, $name)->subject('Enlace de pago — DEZA Voice');
        });
    }

    private function sendWelcomeEmail(string $email, string $name, string $temporaryPassword, Organization $organization, Plan $plan, int $monthlyCents): void
    {
        $loginUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login';

        $html = $this->emailLayout(
            title: 'Bienvenido a DEZA Voice',
            bodyHtml: "
                <p style=\"margin:0 0 16px;\">Hola {$this->e($name)},</p>
                <p style=\"margin:0 0 16px;\">Tu cuenta con el correo <strong>{$this->e($email)}</strong> ha sido creada. Estos son tus datos de acceso:</p>
                <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:0 0 20px;background:#F4F6F8;border-radius:10px;\">
                    <tr>
                        <td style=\"padding:16px 20px;\">
                            <p style=\"margin:0 0 4px;color:#6B7A87;font-size:13px;\">Correo</p>
                            <p style=\"margin:0 0 14px;color:#091925;font-size:15px;\">{$this->e($email)}</p>
                            <p style=\"margin:0 0 4px;color:#6B7A87;font-size:13px;\">Contraseña temporal</p>
                            <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\">
                                <tr>
                                    <td style=\"background:#FFFFFF;border:1.5px dashed #2DBB7F;border-radius:6px;padding:10px 16px;\">
                                        <span style=\"font-family:'Courier New',monospace;font-size:18px;font-weight:700;letter-spacing:1px;color:#091925;user-select:all;\">{$this->e($temporaryPassword)}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:0 0 20px;\">
                    <tr>
                        <td style=\"border-radius:8px;background:#FF6A00;\">
                            <a href=\"{$this->e($loginUrl)}\" style=\"display:inline-block;padding:14px 28px;color:#FFFFFF;font-weight:600;font-size:15px;text-decoration:none;border-radius:8px;\">Iniciar sesión</a>
                        </td>
                    </tr>
                </table>
                <p style=\"margin:0;color:#6B7A87;font-size:13px;\">Se te pedirá que cambies tu contraseña la primera vez que ingreses.</p>
                <p style=\"margin:12px 0 0;color:#6B7A87;font-size:13px;\">Adjuntamos tu contrato de servicio.</p>
            ",
        );

        $contractPdf = Pdf::loadView('pdf.contract', [
            'organization' => $organization,
            'plan' => $plan,
            'monthlyCents' => $monthlyCents,
            'date' => now()->translatedFormat('d \d\e F \d\e Y'),
        ])->output();

        Mail::html($html, function ($message) use ($email, $name, $organization, $contractPdf) {
            $message->to($email, $name)
                ->subject('Bienvenido a DEZA Voice')
                ->attachData($contractPdf, 'Contrato DEZA Voice - ' . $organization->name . '.pdf', [
                    'mime' => 'application/pdf',
                ]);
        });
    }

    private function emailLayout(string $title, string $bodyHtml): string
    {
        return \App\Services\MailTemplateService::layout($title, $bodyHtml);
    }

    private function e(string $value): string
    {
        return \App\Services\MailTemplateService::e($value);
    }
}