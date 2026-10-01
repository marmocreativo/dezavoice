<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SalesChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Sales overview';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getDateRange(): array
    {
        $preset = $this->filters['preset'] ?? 'current_month';
        $today = Carbon::today();

        return match ($preset) {
            'previous_month' => [
                Carbon::now()->subMonthNoOverflow()->startOfMonth(),
                Carbon::now()->subMonthNoOverflow()->endOfMonth(),
            ],
            'last_3_months' => [$today->copy()->subMonths(3)->startOfDay(), $today->copy()],
            'last_6_months' => [$today->copy()->subMonths(6)->startOfDay(), $today->copy()],
            'last_12_months' => [$today->copy()->subMonths(12)->startOfDay(), $today->copy()],
            'custom' => [
                filled($this->filters['start_date'] ?? null) ? Carbon::parse($this->filters['start_date']) : $today->copy()->startOfMonth(),
                filled($this->filters['end_date'] ?? null) ? Carbon::parse($this->filters['end_date']) : $today->copy(),
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()],
        };
    }

    protected function getData(): array
    {
        [$start, $end] = $this->getDateRange();

        $useWeekly = $start->diffInDays($end) >= 90;

        $user = Filament::auth()->user();
        $teamIds = $user->teamUserIds();

        $sales = Sale::query()
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($teamIds !== null, fn ($q) => $q->whereIn('seller_id', $teamIds))
            ->get(['created_at', 'status']);

        $buckets = [];
        $period = $useWeekly
            ? CarbonPeriod::create($start->copy()->startOfWeek(), '1 week', $end)
            : CarbonPeriod::create($start->copy(), '1 day', $end);

        foreach ($period as $date) {
            $key = $useWeekly ? $date->format('o-\WW') : $date->format('Y-m-d');
            $buckets[$key] = [
                'label' => $useWeekly ? 'Wk of ' . $date->format('M j') : $date->format('M j'),
                'pending' => 0,
                'paid' => 0,
            ];
        }

        foreach ($sales as $sale) {
            $date = Carbon::parse($sale->created_at);
            $key = $useWeekly ? $date->copy()->startOfWeek()->format('o-\WW') : $date->format('Y-m-d');

            if (! isset($buckets[$key])) {
                continue;
            }

            if ($sale->status === 'pagada') {
                $buckets[$key]['paid']++;
            } else {
                $buckets[$key]['pending']++;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pending / Confirmed',
                    'data' => array_column($buckets, 'pending'),
                    'backgroundColor' => '#9CA3AF',
                ],
                [
                    'label' => 'Paid',
                    'data' => array_column($buckets, 'paid'),
                    'backgroundColor' => '#2DD4A7',
                ],
            ],
            'labels' => array_column($buckets, 'label'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true],
            ],
        ];
    }
}