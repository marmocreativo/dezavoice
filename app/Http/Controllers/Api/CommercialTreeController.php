<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\SalesProspect;
use Illuminate\Http\Request;

class CommercialTreeController extends Controller
{
    private const WON = ['venta_ganada', 'activacion', 'cliente_activo'];
    private const LOST = ['perdido', 'cancelado', 'reembolsado'];
    private const PENDING = ['nuevo', 'calificado', 'demo_programada', 'demo_realizada', 'propuesta_enviada', 'pago_pendiente'];

    public function index(Request $request)
    {
        /** @var Membership $actor */
        $actor = $request->attributes->get('activeMembership');

        $marketMemberships = Membership::where('market_id', $actor->market_id)
            ->with(['user:id,name', 'salesTeam:id,uuid,name'])
            ->withCount(['sales as sales_count' => fn ($q) => $q->where('status', 'venta_ganada')])
            ->get();

        $byRole = fn (string $role) => $marketMemberships->where('role', $role)->values();

        $tree = $byRole('manager')->map(function (Membership $manager) use ($marketMemberships) {
            $supervisors = $marketMemberships
                ->where('role', 'supervisor')
                ->filter(fn ($s) => in_array($s->id, $manager->descendantIds(), true))
                ->map(function (Membership $supervisor) use ($marketMemberships) {
                    $sellerIds = $marketMemberships
                        ->where('role', 'seller')
                        ->filter(fn ($s) => in_array($s->id, $supervisor->descendantIds(), true))
                        ->pluck('id');

                    $sellers = $marketMemberships
                        ->whereIn('id', $sellerIds)
                        ->map(fn (Membership $s) => [
                            'uuid' => $s->uuid,
                            'name' => $s->user->name,
                            'sales_count' => $s->sales_count,
                        ])->values();

                    $counts = SalesProspect::whereIn('owner_membership_id', $sellerIds)
                        ->selectRaw('status, count(*) as total')
                        ->groupBy('status')
                        ->pluck('total', 'status');

                    $won = $counts->only(self::WON)->sum();
                    $lost = $counts->only(self::LOST)->sum();
                    $pending = $counts->only(self::PENDING)->sum();
                    $total = $won + $lost + $pending;

                    return [
                        'uuid' => $supervisor->uuid,
                        'name' => $supervisor->user->name,
                        'sales_count' => $supervisor->sales_count,
                        'sales_team' => $supervisor->salesTeam ? [
                            'uuid' => $supervisor->salesTeam->uuid,
                            'name' => $supervisor->salesTeam->name,
                        ] : null,
                        'sellers' => $sellers,
                        'prospects_total' => $total,
                        'prospects_won_count' => $won,
                        'prospects_lost_count' => $lost,
                        'prospects_pending_count' => $pending,
                        'prospects_won_percent' => $total > 0 ? round($won / $total * 100, 1) : 0,
                        'prospects_lost_percent' => $total > 0 ? round($lost / $total * 100, 1) : 0,
                        'prospects_pending_percent' => $total > 0 ? round($pending / $total * 100, 1) : 0,
                    ];
                })->values();

            return [
                'uuid' => $manager->uuid,
                'name' => $manager->user->name,
                'sales_count' => $manager->sales_count,
                'supervisors' => $supervisors,
            ];
        });

        return response()->json(['data' => $tree]);
    }
}