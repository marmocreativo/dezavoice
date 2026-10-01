<?php

namespace App\Services;

use App\Models\SalesProspect;
use Illuminate\Support\Collection;

class SalesStatsService
{
    public const WON = ['venta_ganada', 'activacion', 'cliente_activo'];
    public const LOST = ['perdido', 'cancelado', 'reembolsado'];
    public const PENDING = ['nuevo', 'calificado', 'demo_programada', 'demo_realizada', 'propuesta_enviada', 'pago_pendiente'];

    /**
     * Estadísticas por vendedor y totales.
     * Espera membresías con `sales_count` y `sales_cents` ya calculados.
     *
     * @param  Collection<int, \App\Models\Membership>  $sellers
     * @return array{rows: array<int, array<string, int|float>>, totals: array<string, int|float>}
     */
    public function forSellers(Collection $sellers): array
    {
        $counts = SalesProspect::query()
            ->whereIn('owner_membership_id', $sellers->pluck('id'))
            ->selectRaw('owner_membership_id, status, count(*) as total')
            ->groupBy('owner_membership_id', 'status')
            ->get()
            ->groupBy('owner_membership_id');

        $rows = [];
        $sum = ['won' => 0, 'lost' => 0, 'pending' => 0, 'sales_count' => 0, 'sales_cents' => 0];

        foreach ($sellers as $seller) {
            $byStatus = $counts->get($seller->id, collect())->pluck('total', 'status');

            $won = (int) $byStatus->only(self::WON)->sum();
            $lost = (int) $byStatus->only(self::LOST)->sum();
            $pending = (int) $byStatus->only(self::PENDING)->sum();
            $salesCount = (int) ($seller->sales_count ?? 0);
            $salesCents = (int) ($seller->sales_cents ?? 0);

            $rows[$seller->id] = $this->build($won, $lost, $pending, $salesCount, $salesCents);

            $sum['won'] += $won;
            $sum['lost'] += $lost;
            $sum['pending'] += $pending;
            $sum['sales_count'] += $salesCount;
            $sum['sales_cents'] += $salesCents;
        }

        return [
            'rows' => $rows,
            'totals' => $this->build($sum['won'], $sum['lost'], $sum['pending'], $sum['sales_count'], $sum['sales_cents']),
        ];
    }

    private function build(int $won, int $lost, int $pending, int $salesCount, int $salesCents): array
    {
        $total = $won + $lost + $pending;

        return [
            'won' => $won,
            'lost' => $lost,
            'pending' => $pending,
            'total' => $total,
            'won_percent' => $total > 0 ? round($won / $total * 100, 1) : 0,
            'sales_count' => $salesCount,
            'sales_cents' => $salesCents,
        ];
    }
}