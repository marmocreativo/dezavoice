<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionProspectRequest;
use App\Http\Resources\SalesProspectResource;
use App\Models\SalesProspect;
use App\Services\ProspectStatusService;

class ProspectTransitionController extends Controller
{
    public function __construct(
        private readonly ProspectStatusService $statusService,
    ) {}

    public function store(TransitionProspectRequest $request, SalesProspect $prospect)
    {
        $actor = $request->attributes->get('activeMembership');

        try {
            $updated = $this->statusService->transitionManually(
                prospect: $prospect,
                toStatus: $request->validated('to_status'),
                actor: $actor,
                note: $request->validated('note'),
                lostReason: $request->validated('lost_reason'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($request->validated('to_status') === 'pausado') {
            $updated->update(['next_action_at' => $request->validated('next_action_at')]);
        }

        return new SalesProspectResource($updated->fresh());
    }
}