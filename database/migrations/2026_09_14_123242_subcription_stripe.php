<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->string('stripe_subscription_id')->nullable()->unique()->after('opportunity_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opportunity_id');
            $table->dropColumn('stripe_subscription_id');
        });
    }
};