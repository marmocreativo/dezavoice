<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Filament::auth()->user();
        $stats = [];

        $myCount = Sale::where('seller_id', $user->id)->count();

        $stats[] = Stat::make('My sales', $myCount)
            ->url(SaleResource::getUrl('index'))
            ->color('primary');

        $zonaNode = $user->getZonaNode();

        if ($zonaNode) {
            $zonaCount = Sale::whereIn('seller_id', $zonaNode->teamUserIds())->count();

            $stats[] = Stat::make('Total sales in my zone', $zonaCount)
                ->description($zonaNode->zona_nombre ?? 'Zone')
                ->url(SaleResource::getUrl('index'))
                ->color('warning');
        }

        $localNode = $user->getLocalNode();

        if ($localNode) {
            $localCount = Sale::whereIn('seller_id', $localNode->teamUserIds())->count();

            $stats[] = Stat::make('Total sales in my local', $localCount)
                ->description($localNode->local_nombre ?? 'Local')
                ->url(SaleResource::getUrl('index'))
                ->color('success');
        }

        return $stats;
    }
}