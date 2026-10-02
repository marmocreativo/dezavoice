<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::table('retell_calls', function (Blueprint $table) {
            $table->string('from_number', 30)->nullable()->after('canal');
            $table->string('to_number', 30)->nullable()->after('from_number');
        });
    }

    public function down(): void
    {
        Schema::table('retell_calls', function (Blueprint $table) {
            $table->dropColumn(['from_number', 'to_number']);
        });
    }
};
