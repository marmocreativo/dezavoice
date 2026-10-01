<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MembershipSearchResource;
use App\Models\Membership;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function search(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $role = $request->query('role');
        $search = $request->query('q');

        $query = Membership::query()
            ->with('user')
            ->where('status', 'active')
            ->when($role, fn ($q) => $q->where('role', $role));

        // Alcance: nadie fuera de deza_admin puede buscar fuera de su propio mercado
        if ($actor->role !== 'deza_admin') {
            $query->where('market_id', $actor->market_id);
        }

        if ($search) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        $memberships = $query->limit(20)->get();

        return MembershipSearchResource::collection($memberships);
    }
}