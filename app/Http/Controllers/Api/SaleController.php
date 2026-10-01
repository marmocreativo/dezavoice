<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Membership;
use App\Models\Sale;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $visibleIds = Membership::visibleIdsFor($actor);
        $search = $request->query('search');

        $sales = Sale::query()
            ->with(['opportunity.prospect', 'opportunity.subscriptionPlan', 'seller.user'])
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('seller_membership_id', $visibleIds))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->whereHas('opportunity.prospect', fn ($q) => $q->where('business_name', 'like', "%{$search}%"))
                        ->orWhereHas('seller.user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('validated_at')
            ->get();

        return SaleResource::collection($sales);
    }
}