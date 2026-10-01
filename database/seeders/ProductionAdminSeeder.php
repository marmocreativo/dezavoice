<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'marmocreativo@gmail.com'],
            [
                'name' => 'Manuel',
                'password' => 'Angeles1#',
            ]
        );

        Membership::firstOrCreate(
            ['user_id' => $user->id, 'role' => 'deza_admin'],
            ['status' => 'active']
        );

        echo "Usuario creado: {$user->email}\n";
    }
}