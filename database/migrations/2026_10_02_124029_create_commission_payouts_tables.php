<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('membership_id')->constrained('memberships');
            $table->char('currency', 3);
            $table->bigInteger('total_cents');
            $table->unsignedInteger('entries_count');
            $table->date('paid_at');
            $table->string('method', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->json('entries_snapshot')->nullable();
            $table->foreignId('created_by_membership_id')->nullable()->constrained('memberships');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_membership_id')->nullable()->constrained('memberships');
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['membership_id', 'paid_at']);
        });

        Schema::create('commission_payout_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_payout_id')->constrained('commission_payouts')->cascadeOnDelete();
            // Único: un asiento no puede estar en dos pagos activos.
            $table->foreignId('commission_ledger_id')->unique()->constrained('commission_ledger');
            $table->bigInteger('amount_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payout_entries');
        Schema::dropIfExists('commission_payouts');
    }
};
