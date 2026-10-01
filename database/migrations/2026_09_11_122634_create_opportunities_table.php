<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sales_prospect_id')->constrained('sales_prospects');
            $table->foreignId('plan_id')->nullable();
            $table->bigInteger('expected_amount_cents')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('stage');
            $table->unsignedTinyInteger('probability')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};