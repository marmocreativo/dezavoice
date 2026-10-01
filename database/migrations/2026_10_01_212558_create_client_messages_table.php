<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions');
            $table->foreignId('plan_id')->nullable()->constrained('plans');
            $table->timestamp('fecha');
            $table->decimal('minutos_consumidos', 10, 2)->default(0);
            $table->text('mensaje');
            $table->timestamps();

            $table->index(['organization_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_messages');
    }
};
