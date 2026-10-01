<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefundPaymentRequest;
use App\Http\Resources\CommissionLedgerResource;
use App\Models\Membership;
use App\Models\Payment;
use App\Services\CommissionReverser;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentRefundController extends Controller
{
    public function __construct(
        private readonly CommissionReverser $reverser,
    ) {}

    public function store(RefundPaymentRequest $request, Payment $payment)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        if (! in_array($actor->role, ['manager', 'deza_admin'], true)) {
            abort(403, 'Solo un gerente o administrador puede procesar reembolsos.');
        }

        try {
            $reversals = $this->reverser->reverse($payment, $request->validated('reason'), $actor);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return CommissionLedgerResource::collection(collect($reversals));
    }
}