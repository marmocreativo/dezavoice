<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider');
            $table->string('external_event_id');
            $table->string('event_type')->nullable();
            $table->string('status');
            $table->json('payload')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['provider', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};