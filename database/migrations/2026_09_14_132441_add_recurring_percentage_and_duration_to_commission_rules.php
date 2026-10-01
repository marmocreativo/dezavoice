<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->decimal('recurring_percentage', 5, 2)->nullable()->after('recurring_amount_cents');
            $table->unsignedInteger('duration_months')->nullable()->after('recurring_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->dropColumn(['recurring_percentage', 'duration_months']);
        });
    }
};