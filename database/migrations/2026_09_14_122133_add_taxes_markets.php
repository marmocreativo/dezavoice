<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->string('tax_name')->nullable()->after('currency'); // ej. "IGV", "IVA"
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_name'); // ej. 18.00
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropColumn(['tax_name', 'tax_rate']);
        });
    }
};