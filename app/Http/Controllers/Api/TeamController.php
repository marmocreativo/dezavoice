<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\SalesProspect;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    private const WON = ['venta_ganada', 'activacion', 'cliente_activo'];
    private const LOST = ['perdido', 'cancelado', 'reembolsado'];
    private const PENDING = ['nuevo', 'calificado', 'demo_programada', 'demo_realizada', 'propuesta_enviada', 'pago_pendiente'];

    public function members(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $sellerIds = Membership::whereIn('id', $actor->descendantIds())->where('role', 'seller')->pluck('id');

        $members = Membership::whereIn('id', $sellerIds)
            ->with('user:id,name,avatar_path')
            ->withCount(['sales as sales_count' => fn ($q) => $q->where('status', 'venta_ganada')])
            ->get()
            ->map(function (Membership $m) {
                $counts = SalesProspect::where('owner_membership_id', $m->id)
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $won = $counts->only(self::WON)->sum();
                $lost = $counts->only(self::LOST)->sum();
                $pending = $counts->only(self::PENDING)->sum();
                $total = $won + $lost + $pending;

                return [
                    'uuid' => $m->uuid,
                    'codigo' => $m->codigo,
                    'name' => $m->user->name,
                    'sales_count' => $m->sales_count,
                    'prospects_total' => $total,
                    'prospects_won_count' => $won,
                    'prospects_lost_count' => $lost,
                    'prospects_pending_count' => $pending,
                    'prospects_won_percent' => $total > 0 ? round($won / $total * 100, 1) : 0,
                    'prospects_lost_percent' => $total > 0 ? round($lost / $total * 100, 1) : 0,
                    'prospects_pending_percent' => $total > 0 ? round($pending / $total * 100, 1) : 0,
                ];
            });

        return response()->json(['data' => $members]);
    }

    public function member(Request $request, string $uuid)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $member = Membership::where('uuid', $uuid)->firstOrFail();

        abort_unless(in_array($member->id, $actor->descendantIds(), true), 403, 'No tienes acceso a esta membresía.');

        $prospects = SalesProspect::where('owner_membership_id', $member->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['uuid', 'business_name', 'status', 'next_action_at']);

        return response()->json([
            'data' => [
                'uuid' => $member->uuid,
                'name' => $member->user->name,
                'codigo' => $member->codigo,
                'recent_prospects' => $prospects,
            ],
        ]);
    }

    public function funnel(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $counts = SalesProspect::whereIn('owner_membership_id', $actor->descendantIds())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json(['data' => $counts]);
    }
}