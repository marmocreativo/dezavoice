<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->after('nombre_negocio');
            $table->string('tipo_solicitud', 20)->default('solicitud')->after('descripcion');
        });

        // Los perfiles que ya existían son de restaurantes: registran pedidos.
        DB::table('agent_profiles')->update(['tipo_solicitud' => 'pedido']);
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->dropColumn(['descripcion', 'tipo_solicitud']);
        });
    }
};
