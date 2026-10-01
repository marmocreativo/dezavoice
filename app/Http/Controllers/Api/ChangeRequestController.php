<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChangeRequestRequest;
use App\Models\ChangeRequest;
use App\Models\Membership;
use App\Models\Notification;
use Illuminate\Http\Request;

class ChangeRequestController extends Controller
{
    public function store(StoreChangeRequestRequest $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $changeRequest = ChangeRequest::create([
            'membership_id' => $actor->id,
            'subject' => $request->validated('subject'),
            'description' => $request->validated('description'),
            'status' => 'open',
        ]);

        Membership::where('role', 'deza_admin')->active()->get()->each(function (Membership $admin) use ($changeRequest, $actor) {
            Notification::create([
                'membership_id' => $admin->id,
                'type' => 'change_request.created',
                'title' => 'Nueva solicitud de cambio',
                'body' => "{$actor->user->name} solicitó: {$changeRequest->subject}",
                'data' => ['change_request_uuid' => $changeRequest->uuid],
            ]);
        });

        return response()->json(['data' => ['uuid' => $changeRequest->uuid, 'status' => $changeRequest->status]], 201);
    }
}