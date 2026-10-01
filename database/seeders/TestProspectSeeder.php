<?php

namespace Database\Seeders;

use App\Models\Market;
use App\Models\Membership;
use App\Models\SalesProspect;
use Illuminate\Database\Seeder;

class TestProspectSeeder extends Seeder
{
    public function run(): void
    {
        $market = Market::firstOrCreate(
            ['code' => 'PE'],
            ['name' => 'Perú', 'currency' => 'PEN', 'timezone' => 'America/Lima']
        );

        $adminMembership = Membership::first();

        SalesProspect::create([
            'owner_membership_id' => $adminMembership->id,
            'market_id' => $market->id,
            'business_name' => 'Restaurante La Prueba',
            'giro' => 'alimentos_bebidas',
            'status' => 'nuevo',
        ]);
    }
}