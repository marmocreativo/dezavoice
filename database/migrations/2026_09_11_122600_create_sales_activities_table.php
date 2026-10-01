<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sales_prospect_id')->constrained('sales_prospects');
            $table->foreignId('membership_id')->constrained('memberships');
            $table->string('type');
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->decimal('checkin_lat', 10, 7)->nullable();
            $table->decimal('checkin_lng', 10, 7)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_activities');
    }
};