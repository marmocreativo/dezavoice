<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Opportunity;
use App\Services\ProspectStatusService;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function __construct(
        private readonly ProspectStatusService $statusService,
    ) {}

    public function store(StoreQuoteRequest $request, Opportunity $opportunity)
    {
        $quote = DB::transaction(function () use ($request, $opportunity) {
            $quote = $opportunity->quotes()->create([
                'amount_cents' => $request->validated('amount_cents'),
                'currency' => $request->validated('currency'),
                'valid_until' => $request->validated('valid_until'),
                'sent_at' => now(),
            ]);

            $prospect = $opportunity->prospect;

            if ($prospect->status === 'demo_realizada') {
                $this->statusService->transitionManually(
                    prospect: $prospect,
                    toStatus: 'propuesta_enviada',
                    actor: request()->attributes->get('activeMembership'),
                    note: 'Cotización v' . $quote->version . ' generada.',
                );
            }

            return $quote;
        });

        return new QuoteResource($quote);
    }
}