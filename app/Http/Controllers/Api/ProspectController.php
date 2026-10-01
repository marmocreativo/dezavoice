<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\UpdateProspectRequest;
use App\Http\Resources\SalesProspectResource;
use App\Models\Membership;
use App\Models\SalesProspect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProspectController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $visibleIds = Membership::visibleIdsFor($actor);

        $query = SalesProspect::query()
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('owner_membership_id', $visibleIds));

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        $prospects = $query->orderByDesc('created_at')->cursorPaginate(20);

        return SalesProspectResource::collection($prospects);
    }

    public function store(StoreProspectRequest $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $prospect = DB::transaction(function () use ($request, $actor) {
            $prospect = SalesProspect::create([
                'owner_membership_id' => $actor->id,
                'market_id' => $actor->market_id,
                'business_name' => $request->validated('business_name'),
                'giro' => $request->validated('giro'),
                'address' => $request->validated('address'),
                'lat' => $request->validated('lat'),
                'lng' => $request->validated('lng'),
                'status' => 'nuevo',
            ]);

            foreach ($request->validated('contacts', []) as $contact) {
                $prospect->contacts()->create($contact);
            }

            return $prospect;
        });

        return new SalesProspectResource($prospect->load('contacts'));
    }

    public function show(Request $request, SalesProspect $prospect)
    {
        $this->authorizeVisibility($request, $prospect);

        return new SalesProspectResource($prospect->load('contacts'));
    }

    public function update(UpdateProspectRequest $request, SalesProspect $prospect)
    {
        $this->authorizeVisibility($request, $prospect);

        $prospect->update($request->validated());

        return new SalesProspectResource($prospect->fresh()->load('contacts'));
    }

    private function authorizeVisibility(Request $request, SalesProspect $prospect): void
    {
        $actor = $request->attributes->get('activeMembership');

        $this->authorize('view', [$prospect, $actor]);
    }
}