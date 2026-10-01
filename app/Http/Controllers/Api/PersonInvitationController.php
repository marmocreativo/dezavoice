<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvitePersonRequest;
use App\Http\Resources\MembershipDetailResource;
use App\Models\Membership;
use App\Services\PersonInvitationService;
use RuntimeException;

class PersonInvitationController extends Controller
{
    public function __construct(
        private readonly PersonInvitationService $service,
    ) {}

    public function store(InvitePersonRequest $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        try {
            $membership = $this->service->invite($actor, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return new MembershipDetailResource($membership->load('user'));
    }
}