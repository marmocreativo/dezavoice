<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_progress', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('membership_id')->constrained('memberships');
            $table->foreignId('commission_rule_id')->constrained('commission_rules');
            $table->string('period');
            $table->unsignedInteger('current_value')->default(0);
            $table->unsignedInteger('target_value');
            $table->boolean('is_met')->default(false);
            $table->timestamp('computed_at');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_progress');
    }
};