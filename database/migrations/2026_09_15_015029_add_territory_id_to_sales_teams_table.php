<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_teams', function (Blueprint $table) {
            $table->foreignId('territory_id')->nullable()->after('market_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('territory_id');
        });
    }
};