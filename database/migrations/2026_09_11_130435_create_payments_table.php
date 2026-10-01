<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices');
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities');
            $table->string('provider');
            $table->string('external_id');
            $table->string('idempotency_key');
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status');
            $table->timestamp('confirmed_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['provider', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};