<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Manuel',
            'email' => 'marmocreativo@gmail.com',
            'password' => 'Angeles1#',
        ]);

        Membership::create([
            'user_id' => $user->id,
            'role' => 'deza_admin',
            'status' => 'active',
        ]);
    }
}