<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request Management module (spec 0049, D-4): dynamic-field values collected
 * per Opportunity, keyed by Attribute `code` — the union (dedup-per-code) of
 * the effective Attributes of every product-category on the Opportunity's
 * product lines. Written EXCLUSIVELY by RequestManagementService, never
 * mass-assignable (deliberately absent from Opportunity's #[Fillable]).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->json('attribute_values')->nullable()->after('opportunity_workflow_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn('attribute_values');
        });
    }
};
