<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::create('menu_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sales_prospect_id')->constrained('sales_prospects');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by_membership_id')->nullable()->constrained('memberships');
            $table->timestamps();
            $table->softDeletes();

            $table->index('sales_prospect_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_photos');
    }
};
