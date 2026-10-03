<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->foreignId('sales_prospect_id')->nullable()->unique()->after('organization_id')->constrained('sales_prospects');
            $table->unsignedBigInteger('organization_id')->nullable()->change();
        });

        // Los perfiles creados desde el panel (por organización) pasan a su prospecto.
        DB::table('agent_profiles')->whereNull('sales_prospect_id')->orderBy('id')->each(function ($profile) {
            $prospectId = DB::table('subscriptions')
                ->join('opportunities', 'opportunities.id', '=', 'subscriptions.opportunity_id')
                ->where('subscriptions.organization_id', $profile->organization_id)
                ->orderByDesc('subscriptions.id')
                ->value('opportunities.sales_prospect_id');

            if ($prospectId && ! DB::table('agent_profiles')->where('sales_prospect_id', $prospectId)->exists()) {
                DB::table('agent_profiles')->where('id', $profile->id)->update(['sales_prospect_id' => $prospectId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_prospect_id');
        });
    }
};
