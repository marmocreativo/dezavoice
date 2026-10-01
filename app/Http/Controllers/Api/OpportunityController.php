<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Resources\OpportunityResource;
use App\Models\Membership;
use App\Models\Opportunity;
use App\Models\SalesProspect;
use Illuminate\Http\Request;

class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $opportunities = Opportunity::query()
            ->whereHas('prospect', function ($query) use ($actor) {
                $visibleIds = $this->visibleMembershipIds($actor);

                if ($visibleIds !== null) {
                    $query->whereIn('owner_membership_id', $visibleIds);
                }
            })
            ->with('prospect')
            ->orderByDesc('created_at')
            ->cursorPaginate(20);

        return OpportunityResource::collection($opportunities);
    }

    public function store(StoreOpportunityRequest $request, SalesProspect $prospect)
    {
        $opportunity = $prospect->opportunities()->create([
            'expected_amount_cents' => $request->validated('expected_amount_cents'),
            'currency' => $request->validated('currency'),
            'stage' => 'open',
            'probability' => $request->validated('probability'),
        ]);

        return new OpportunityResource($opportunity);
    }

    private function visibleMembershipIds(Membership $actor): ?array
    {
        return Membership::visibleIdsFor($actor);
    }
}