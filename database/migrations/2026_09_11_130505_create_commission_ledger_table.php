<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('membership_id')->constrained('memberships');
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->foreignId('payment_id')->nullable()->constrained('payments');
            $table->foreignId('commission_rule_id')->constrained('commission_rules');
            $table->string('entry_type');
            $table->string('status')->default('pending');
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->json('calculation_snapshot')->nullable();
            $table->foreignId('reverses_ledger_id')->nullable()->constrained('commission_ledger');
            $table->text('note')->nullable();
            $table->foreignId('created_by_membership_id')->nullable()->constrained('memberships');
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_ledger');
    }
};