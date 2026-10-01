<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignProspectRequest;
use App\Http\Resources\SalesProspectResource;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\SalesProspect;
use Illuminate\Http\Request;

class ProspectAssignmentController extends Controller
{
    public function store(AssignProspectRequest $request, SalesProspect $prospect)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $this->authorize('assign', [$prospect, $actor]);

        $newOwner = Membership::where('uuid', $request->validated('membership_uuid'))
            ->active()
            ->firstOrFail();

        if (! in_array($newOwner->id, $actor->descendantIds(), true) && $actor->role !== 'deza_admin') {
            abort(403, 'El nuevo dueño debe pertenecer a tu equipo.');
        }

        $prospect->update(['owner_membership_id' => $newOwner->id]);

        Notification::create([
            'membership_id' => $newOwner->id,
            'type' => 'prospect.assigned',
            'title' => 'Nuevo prospecto asignado',
            'body' => "Se te asignó {$prospect->business_name}.",
            'data' => ['prospect_uuid' => $prospect->uuid],
        ]);

        return new SalesProspectResource($prospect->fresh());
    }
}