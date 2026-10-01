<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::create('agent_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->unique()->constrained('organizations');
            $table->string('nombre_negocio')->nullable();
            $table->string('direccion')->nullable();
            $table->string('horario')->nullable();
            $table->string('tiempo_preparacion', 100)->nullable();
            $table->text('formas_de_pago')->nullable();
            $table->text('menu')->nullable();
            $table->text('instrucciones_adicionales')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_profiles');
    }
};
