<?php

namespace App\Filament\Resources\Users\Schemas;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('avatar_path')
                    ->label('Photo')
                    ->image()
                    ->avatar()
                    ->imageEditor()
                    ->imageEditorAspectRatios(['1:1'])
                    ->circleCropper()
                    ->directory('avatars')
                    ->visibility('public')
                    ->saveUploadedFileUsing(function ($file) {
                        $manager = ImageManager::usingDriver(Driver::class);

                        $image = $manager->decodeSplFileInfo($file)
                            ->cover(400, 400);

                        $encoded = $image->encodeUsingFormat(Format::WEBP, quality: 85);

                        $filename = 'avatars/' . Str::uuid() . '.webp';
                        Storage::disk('public')->put($filename, (string) $encoded);

                        return $filename;
                    }),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->label(fn (string $operation): string => $operation === 'create' ? 'Password' : 'New password (optional)'),
                Select::make('nivel')
                    ->label('Level')
                    ->options([
                        0 => 'Super Admin',
                        1 => 'Level 1 (Zone)',
                        2 => 'Level 2 (Local)',
                        3 => 'Level 3 (Seller)',
                    ])
                    ->required()
                    ->default(3)
                    ->live()
                    ->native(false),
                Select::make('parent_id')
                    ->label('Manager')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                TextInput::make('zona_nombre')
                    ->label('Zone name')
                    ->visible(fn (Get $get): bool => (int) $get('nivel') === 1),
                TextInput::make('local_nombre')
                    ->label('Local name')
                    ->visible(fn (Get $get): bool => (int) $get('nivel') === 2),
            ]);
    }
}