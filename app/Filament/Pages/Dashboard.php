<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('preset')
                    ->label('Date range')
                    ->options([
                        'current_month' => 'Current month',
                        'previous_month' => 'Previous month',
                        'last_3_months' => 'Last 3 months',
                        'last_6_months' => 'Last 6 months',
                        'last_12_months' => 'Last 12 months',
                        'custom' => 'Custom range',
                    ])
                    ->default('current_month')
                    ->live()
                    ->native(false),
                DatePicker::make('start_date')
                    ->label('Start date')
                    ->visible(fn (Get $get): bool => $get('preset') === 'custom'),
                DatePicker::make('end_date')
                    ->label('End date')
                    ->visible(fn (Get $get): bool => $get('preset') === 'custom'),
            ])
            ->columns(3);
    }
}