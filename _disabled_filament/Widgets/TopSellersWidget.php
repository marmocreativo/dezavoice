<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopSellersWidget extends BaseWidget
{
    protected static ?string $heading = 'Top sellers';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return in_array($user->nivel, [1, 2], true);
    }

    public function table(Table $table): Table
    {
        $user = Filament::auth()->user();
        $teamIds = $user->teamUserIds() ?? [];

        return $table
            ->query(
                User::query()
                    ->whereIn('id', $teamIds)
                    ->where('nivel', 3)
                    ->withCount('sales')
                    ->orderByDesc('sales_count')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Seller'),
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Code')
                    ->badge(),
                Tables\Columns\TextColumn::make('sales_count')
                    ->label('Sales')
                    ->sortable(),
            ])
            ->paginated([5, 10]);
    }
}