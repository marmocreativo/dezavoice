<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('opportunity_id')->constrained('opportunities');
            $table->foreignId('seller_membership_id')->constrained('memberships');
            $table->foreignId('payment_id')->nullable();
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->timestamp('validated_at')->nullable();
            $table->string('status')->default('venta_ganada');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};