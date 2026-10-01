<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->header('X-Membership-Uuid');

        $membership = Membership::where('user_id', $request->user()->id)
            ->when($uuid, fn ($query) => $query->where('uuid', $uuid))
            ->active()
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => $uuid
                    ? 'La membresía indicada no existe o no está activa.'
                    : 'El usuario no tiene ninguna membresía activa.',
            ], 403);
        }

        $request->attributes->set('activeMembership', $membership);

        return $next($request);
    }
}