<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\MembershipResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Auth::validate(['email' => $request->validated('email'), 'password' => $request->validated('password')])) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        $memberships = $user->activeMemberships()->get();

        return response()->json([
            'token' => $user->createToken('pwa')->plainTextToken,
            'user' => new UserResource($user),
            'memberships' => MembershipResource::collection($memberships),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
            'memberships' => MembershipResource::collection($user->activeMemberships()->get()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function changePassword(\App\Http\Requests\ChangePasswordRequest $request)
    {
        $user = $request->user();

        $user->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ]);

        return response()->json(['message' => 'Contraseña actualizada.']);
    }

    public function updateProfile(\App\Http\Requests\UpdateProfileRequest $request)
    {
        $user = $request->user();

        $user->update($request->validated());

        return new UserResource($user->fresh());
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:4096'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar_path' => $path]);

        return new UserResource($user->fresh());
    }
}