<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->bigInteger('setup_fee_cents')->default(0)->after('price_cents');
        });

        DB::table('plans')->where('code', 'respaldo')->update(['setup_fee_cents' => 39000]);
        DB::table('plans')->where('code', 'operacion')->update(['setup_fee_cents' => 69000]);

        DB::table('plans')->where('code', 'personalizado')->delete();
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('setup_fee_cents');
        });
    }
};