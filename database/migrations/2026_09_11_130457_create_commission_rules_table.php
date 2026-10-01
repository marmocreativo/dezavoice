<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('commission_plan_id')->constrained('commission_plans');
            $table->foreignId('plan_id')->nullable()->constrained('plans');
            $table->bigInteger('activation_amount_cents');
            $table->bigInteger('recurring_amount_cents')->nullable();
            $table->boolean('requires_personal_sale')->default(false);
            $table->unsignedInteger('individual_goal')->nullable();
            $table->unsignedInteger('team_goal')->nullable();
            $table->unsignedInteger('team_size_requirement')->nullable();
            $table->unsignedInteger('qualifying_members_required')->nullable();
            $table->string('payment_condition')->nullable();
            $table->unsignedInteger('waiting_period_days')->default(0);
            $table->unsignedInteger('reversal_window_days')->default(0);
            $table->json('bonus_definition')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};