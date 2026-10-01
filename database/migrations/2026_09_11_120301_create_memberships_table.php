<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('role', ['client', 'seller', 'supervisor', 'manager', 'deza_admin']);
            $table->foreignId('market_id')->nullable()->constrained('markets');
            $table->foreignId('organization_id')->nullable()->constrained('organizations');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->foreignId('sales_team_id')->nullable()->constrained('sales_teams');
            $table->foreignId('territory_id')->nullable()->constrained('territories');
            $table->string('scope')->nullable();
            $table->string('codigo', 3)->nullable()->unique();
            $table->string('status')->default('active');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};