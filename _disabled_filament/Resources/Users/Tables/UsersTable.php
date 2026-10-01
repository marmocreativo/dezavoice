<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('nivel')
                    ->label('Nivel')
                    ->badge()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label('Gerente')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('success'),
                TextColumn::make('sales_count')
                    ->label('Ventas')
                    ->counts('sales')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
