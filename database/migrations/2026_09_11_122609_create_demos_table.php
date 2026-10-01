<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sales_prospect_id')->constrained('sales_prospects');
            $table->foreignId('membership_id')->constrained('memberships');
            $table->timestamp('scheduled_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demos');
    }
};