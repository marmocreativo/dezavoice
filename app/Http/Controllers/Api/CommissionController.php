<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommissionEntryResource;
use App\Models\CommissionLedger;
use App\Models\Membership;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $entries = CommissionLedger::where('membership_id', $actor->id)
            ->with('sale.opportunity.prospect')
            ->orderByDesc('created_at')
            ->get();

        return CommissionEntryResource::collection($entries);
    }
}