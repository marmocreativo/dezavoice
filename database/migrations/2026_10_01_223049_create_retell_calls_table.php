<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::create('retell_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('retell_call_id', 100)->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('subscription_id')->constrained('subscriptions');
            $table->foreignId('plan_id')->nullable()->constrained('plans');
            $table->string('canal', 30)->default('web_test');
            $table->string('status', 20)->default('created'); // created | started | ended
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->decimal('minutos_consumidos', 10, 2)->nullable();
            $table->timestamp('minutos_aplicados_at')->nullable();
            $table->string('disconnection_reason')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retell_calls');
    }
};
