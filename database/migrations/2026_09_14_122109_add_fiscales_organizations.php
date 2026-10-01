<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('tax_id')->nullable()->after('legal_name');
            $table->string('billing_email')->nullable()->after('tax_id');
            $table->string('phone')->nullable()->after('billing_email');
            $table->string('address_line1')->nullable()->after('phone');
            $table->string('city')->nullable()->after('address_line1');
            $table->string('state')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('state');
            $table->char('country', 2)->nullable()->after('postal_code'); // ISO: MX, PE, ES, US
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['legal_name', 'tax_id', 'billing_email', 'phone', 'address_line1', 'city', 'state', 'postal_code', 'country']);
        });
    }
};