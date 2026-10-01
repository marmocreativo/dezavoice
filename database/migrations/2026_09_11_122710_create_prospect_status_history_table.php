<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_status_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sales_prospect_id')->constrained('sales_prospects');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by_membership_id')->nullable()->constrained('memberships');
            $table->text('note')->nullable();
            $table->timestamp('changed_at');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_status_history');
    }
};