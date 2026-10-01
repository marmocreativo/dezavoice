<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Membership;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $notifications = Notification::where('membership_id', $actor->id)
            ->orderByDesc('created_at')
            ->cursorPaginate(20);

        return NotificationResource::collection($notifications);
    }

    public function markRead(Request $request, string $uuid)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $notification = Notification::where('uuid', $uuid)
            ->where('membership_id', $actor->id)
            ->firstOrFail();

        $notification->markAsRead();

        return new NotificationResource($notification);
    }
}