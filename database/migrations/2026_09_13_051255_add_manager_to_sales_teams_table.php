<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_teams', function (Blueprint $table) {
            $table->foreignId('manager_membership_id')->nullable()->after('market_id')->constrained('memberships');
        });
    }

    public function down(): void
    {
        Schema::table('sales_teams', function (Blueprint $table) {
            $table->dropForeign(['manager_membership_id']);
            $table->dropColumn('manager_membership_id');
        });
    }
};