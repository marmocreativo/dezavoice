<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::table('client_messages', function (Blueprint $table) {
            $table->string('retell_call_id', 100)->nullable()->unique()->after('plan_id');
            $table->string('canal', 30)->default('manual')->after('retell_call_id');
            $table->string('tipo', 20)->default('mensaje')->after('canal'); // mensaje | pedido | llamada
        });
    }

    public function down(): void
    {
        Schema::table('client_messages', function (Blueprint $table) {
            $table->dropUnique(['retell_call_id']);
            $table->dropColumn(['retell_call_id', 'canal', 'tipo']);
        });
    }
};
