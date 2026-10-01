<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestHierarchySeeder extends Seeder
{
    public function run(): void
    {
        // Nivel 1 - Zona
        $zona = User::create([
            'name' => 'Zona Centro',
            'email' => 'zona@test.com',
            'password' => 'password',
            'nivel' => 1,
            'parent_id' => null,
        ]);

        // Nivel 2 - Gerente Local (bajo la zona)
        $local = User::create([
            'name' => 'Manuel',
            'email' => 'manuel@test.com',
            'password' => 'password',
            'nivel' => 2,
            'parent_id' => $zona->id,
        ]);

        // Nivel 3 - Vendedor (bajo el gerente local)
        $vendedor = User::create([
            'name' => 'Franco',
            'email' => 'franco@test.com',
            'password' => 'password',
            'nivel' => 3,
            'parent_id' => $local->id,
        ]);

        // Otro vendedor nivel 3, mismo gerente, para probar rollup con más de uno
        $vendedor2 = User::create([
            'name' => 'Lupita',
            'email' => 'lupita@test.com',
            'password' => 'password',
            'nivel' => 3,
            'parent_id' => $local->id,
        ]);

        // Una zona/rama distinta, totalmente separada, para confirmar que NO se cruzan datos
        $zona2 = User::create([
            'name' => 'Zona Norte',
            'email' => 'zonanorte@test.com',
            'password' => 'password',
            'nivel' => 1,
            'parent_id' => null,
        ]);

        $vendedor3 = User::create([
            'name' => 'Carlos',
            'email' => 'carlos@test.com',
            'password' => 'password',
            'nivel' => 3,
            'parent_id' => $zona2->id,
        ]);

        // Ventas de prueba repartidas entre los vendedores
        Sale::create([
            'codigo_usado' => $vendedor->codigo,
            'seller_id' => $vendedor->id,
            'cliente_nombre' => 'Restaurante Los Pinos',
            'cliente_contacto' => '555-111-2222',
            'status' => 'confirmada',
        ]);

        Sale::create([
            'codigo_usado' => $vendedor->codigo,
            'seller_id' => $vendedor->id,
            'cliente_nombre' => 'Clínica Dental Sonrisa',
            'cliente_contacto' => 'contacto@sonrisa.com',
            'status' => 'pendiente',
        ]);

        Sale::create([
            'codigo_usado' => $vendedor2->codigo,
            'seller_id' => $vendedor2->id,
            'cliente_nombre' => 'Taquería El Buen Sabor',
            'cliente_contacto' => '555-333-4444',
            'status' => 'pagada',
        ]);

        Sale::create([
            'codigo_usado' => $local->codigo,
            'seller_id' => $local->id,
            'cliente_nombre' => 'Consultorio Dr. Ramírez',
            'cliente_contacto' => '555-555-6666',
            'status' => 'confirmada',
        ]);

        Sale::create([
            'codigo_usado' => $vendedor3->codigo,
            'seller_id' => $vendedor3->id,
            'cliente_nombre' => 'Café Norte',
            'cliente_contacto' => '555-777-8888',
            'status' => 'pendiente',
        ]);
    }
}