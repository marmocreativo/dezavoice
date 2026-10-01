<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('territories', function (Blueprint $table) {
            $table->foreignId('assigned_manager_membership_id')->nullable()->after('market_id')->constrained('memberships');
        });
    }

    public function down(): void
    {
        Schema::table('territories', function (Blueprint $table) {
            $table->dropForeign(['assigned_manager_membership_id']);
            $table->dropColumn('assigned_manager_membership_id');
        });
    }
};