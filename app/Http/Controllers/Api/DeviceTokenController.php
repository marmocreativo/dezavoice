<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterDeviceRequest;
use App\Models\DeviceToken;

class DeviceTokenController extends Controller
{
    public function store(RegisterDeviceRequest $request)
    {
        $device = DeviceToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $request->validated('endpoint')],
            [
                'p256dh' => $request->validated('keys.p256dh'),
                'auth' => $request->validated('keys.auth'),
                'platform' => $request->validated('platform'),
                'last_used_at' => now(),
            ],
        );

        return response()->json(['data' => ['uuid' => $device->uuid]], 201);
    }
}