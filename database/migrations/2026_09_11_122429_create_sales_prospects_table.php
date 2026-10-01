<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_prospects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_membership_id')->constrained('memberships');
            $table->foreignId('market_id')->constrained('markets');
            $table->string('business_name');
            $table->string('giro')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('status')->default('nuevo');
            $table->string('lost_reason')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('next_action_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_prospects');
    }
};