<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDemoRequest;
use App\Http\Requests\UpdateDemoRequest;
use App\Http\Resources\DemoResource;
use App\Models\Demo;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\ReportingLine;
use App\Models\SalesProspect;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $demos = Demo::query()
            ->whereIn('membership_id', $this->visibleMembershipIds($actor))
            ->orderBy('scheduled_at')
            ->get();

        return DemoResource::collection($demos);
    }

    public function store(StoreDemoRequest $request, SalesProspect $prospect)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $demo = $prospect->demos()->create([
            'membership_id' => $actor->id,
            'scheduled_at' => $request->validated('scheduled_at'),
        ]);

        return new DemoResource($demo);
    }

    public function update(UpdateDemoRequest $request, Demo $demo)
    {
        $wasCompleted = $demo->completed_at !== null;

        $demo->update($request->validated());

        if (! $wasCompleted && $demo->completed_at !== null) {
            $this->notifySupervisor($demo);
        }

        return new DemoResource($demo->fresh());
    }

    private function notifySupervisor(Demo $demo): void
    {
        $line = ReportingLine::active()->where('member_membership_id', $demo->membership_id)->first();

        if (! $line) {
            return;
        }

        Notification::create([
            'membership_id' => $line->manager_membership_id,
            'type' => 'demo.completed',
            'title' => 'Demo completada',
            'body' => "Se completó una demo para {$demo->prospect->business_name} ({$demo->outcome}).",
            'data' => ['demo_uuid' => $demo->uuid, 'prospect_uuid' => $demo->prospect->uuid],
        ]);
    }

    private function visibleMembershipIds(Membership $actor): ?array
    {
        return Membership::visibleIdsFor($actor);
    }
}