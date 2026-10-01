<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\SalesProspect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function seller(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $nextFollowUp = SalesProspect::where('owner_membership_id', $actor->id)
            ->overdue()
            ->orderBy('next_action_at')
            ->first(['uuid', 'business_name', 'status', 'next_action_at']);

        $salesThisMonth = $actor->sales()
            ->where('status', 'venta_ganada')
            ->whereMonth('validated_at', now()->month)
            ->whereYear('validated_at', now()->year)
            ->count();

        $commissionEarned = $actor->commissionLedgerEntries()
            ->whereIn('status', ['earned', 'payable', 'paid'])
            ->sum('amount_cents');

        $commissionPending = $actor->commissionLedgerEntries()
            ->where('status', 'pending')
            ->sum('amount_cents');

        return response()->json([
            'data' => [
                'next_follow_up' => $nextFollowUp,
                'sales_this_month' => $salesThisMonth,
                'commission_earned_cents' => $commissionEarned,
                'commission_pending_cents' => $commissionPending,
                'prospects_overdue_count' => SalesProspect::where('owner_membership_id', $actor->id)->overdue()->count(),
            ],
        ]);
    }

    public function supervisor(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $teamIds = $actor->descendantIds();
        $sellerIds = Membership::whereIn('id', $teamIds)->where('role', 'seller')->pluck('id');

        $performance = Membership::whereIn('id', $sellerIds)
            ->withCount(['sales as sales_count' => fn ($q) => $q->where('status', 'venta_ganada')])
            ->get(['id', 'user_id']);

        $overdueFollowUps = SalesProspect::whereIn('owner_membership_id', $sellerIds)->overdue()->count();

        return response()->json([
            'data' => [
                'team_size' => $sellerIds->count(),
                'sellers_meeting_goal' => $performance->where('sales_count', '>=', 10)->count(),
                'sellers_below_goal' => $performance->where('sales_count', '<', 10)->count(),
                'overdue_follow_ups' => $overdueFollowUps,
            ],
        ]);
    }

    public function manager(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $marketMembershipIds = Membership::where('market_id', $actor->market_id)->pluck('id');

        $from = $request->query('from');
        $to = $request->query('to');

        $salesQuery = DB::table('sales')
            ->whereIn('seller_membership_id', $marketMembershipIds)
            ->where('status', 'venta_ganada');

        if ($from) {
            $salesQuery->where('validated_at', '>=', $from);
        }
        if ($to) {
            $salesQuery->where('validated_at', '<=', $to);
        }

        $salesValidated = $salesQuery->count();

        $mrrCents = DB::table('subscriptions')
            ->join('organizations', 'organizations.id', '=', 'subscriptions.organization_id')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('organizations.market_id', $actor->market_id)
            ->where('subscriptions.status', 'active')
            ->sum('plans.price_cents');

        $activeSellers = Membership::where('market_id', $actor->market_id)
            ->where('role', 'seller')
            ->where('status', 'active')
            ->count();

        $activeSupervisors = Membership::where('market_id', $actor->market_id)
            ->where('role', 'supervisor')
            ->where('status', 'active')
            ->count();

        return response()->json([
            'data' => [
                'mrr_cents' => $mrrCents,
                'sales_validated' => $salesValidated,
                'active_sellers' => $activeSellers,
                'active_supervisors' => $activeSupervisors,
            ],
        ]);
    }
}