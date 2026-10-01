<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Resources\SalesActivityResource;
use App\Models\Membership;
use App\Models\SalesProspect;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request, SalesProspect $prospect)
    {
        $this->authorizeVisibility($request, $prospect);

        $activities = $prospect->activities()->orderByDesc('occurred_at')->get();

        return SalesActivityResource::collection($activities);
    }

    public function store(StoreActivityRequest $request, SalesProspect $prospect)
    {
        $this->authorizeVisibility($request, $prospect);

        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $activity = $prospect->activities()->create([
            'membership_id' => $actor->id,
            'type' => $request->validated('type'),
            'notes' => $request->validated('notes'),
            'occurred_at' => $request->validated('occurred_at') ?? now(),
            'checkin_lat' => $request->validated('checkin_lat'),
            'checkin_lng' => $request->validated('checkin_lng'),
        ]);

        return new SalesActivityResource($activity);
    }

    private function authorizeVisibility(Request $request, SalesProspect $prospect): void
    {
        $actor = $request->attributes->get('activeMembership');

        $this->authorize('view', [$prospect, $actor]);
    }
}