<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Models\User;
use Dotswan\MapPicker\Fields\Map;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SaleForm
{
    protected static array $categories = [
        'alimentos_bebidas' => 'Food & Beverage',
        'salud' => 'Health & Medical',
        'belleza_bienestar' => 'Beauty & Wellness',
        'hospitalidad' => 'Hospitality & Lodging',
        'retail' => 'Retail',
        'servicios_profesionales' => 'Professional Services',
        'educacion' => 'Education',
        'automotriz' => 'Automotive',
        'other' => 'Other',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Seller')
                    ->schema([
                        Placeholder::make('seller_display')
                            ->label('Registering seller')
                            ->content(fn () => Filament::auth()->user()->name . ' (' . Filament::auth()->user()->codigo . ')'),
                        Hidden::make('codigo_usado')
                            ->default(fn () => Filament::auth()->user()->codigo),
                        Hidden::make('seller_id')
                            ->default(fn () => Filament::auth()->user()->id),
                    ])
                    ->columns(1),

                Section::make('Business information')
                    ->schema([
                        TextInput::make('cliente_nombre')
                            ->label('Business name')
                            ->required(),
                        Select::make('giro_categoria')
                            ->label('Business category')
                            ->options(self::$categories)
                            ->native(false)
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && $record->giro) {
                                    $known = array_key_exists($record->giro, self::$categories);
                                    $component->state($known ? $record->giro : 'other');
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('giro', $state === 'other' ? null : $state);
                            }),
                        TextInput::make('giro')
                            ->label('Custom category')
                            ->required()
                            ->visible(fn (Get $get): bool => $get('giro_categoria') === 'other'),
                        TextInput::make('business_address')
                            ->label('Address')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Main contact')
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Contact name'),
                        TextInput::make('contact_phone')
                            ->label('Phone')
                            ->tel(),
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email(),
                    ])
                    ->columns(3),

                Section::make('Location')
                    ->schema([
                        Map::make('location')
                            ->label('Drag the pin to the exact location')
                            ->columnSpanFull()
                            ->defaultLocation(latitude: 19.4326, longitude: -99.1332)
                            ->draggable(true)
                            ->clickable(true)
                            ->zoom(14)
                            ->tilesUrl('https://tile.openstreetmap.org/{z}/{x}/{y}.png')
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record?->latitude && $record?->longitude) {
                                    $component->state([
                                        'lat' => (float) $record->latitude,
                                        'lng' => (float) $record->longitude,
                                    ]);
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('latitude', $state['lat'] ?? null);
                                $set('longitude', $state['lng'] ?? null);
                            })
                            ->live()
                            ->dehydrated(false),
                        Hidden::make('latitude'),
                        Hidden::make('longitude'),
                    ]),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pendiente' => 'Pending',
                        'confirmada' => 'Confirmed',
                        'pagada' => 'Paid',
                    ])
                    ->required()
                    ->default('pendiente')
                    ->native(false),
            ]);
    }
}