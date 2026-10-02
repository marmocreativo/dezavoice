<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::create('client_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('phone_number', 20)->unique();      // E.164, ej. +12137771235
            $table->string('label', 100)->nullable();
            $table->string('forwarded_from', 20)->nullable();  // línea original del negocio que desvía
            $table->string('retell_agent_id', 100)->nullable(); // agente propio de este número (opcional)
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_phone_numbers');
    }
};
