<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-convert-to-Opportunity during lead import (spec 0045): the operator's
 * confirm-step choice, persisted on the run so the commit phase
 * (ProcessStagedImportJob -> LeadsImportDefinition::persistRow()) knows
 * whether a CREATE-branch row should also spawn an Opportunity
 * (ConvertLeadToOpportunity, spec 0044). Defaults false so every existing
 * run behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->boolean('convert_to_opportunity')->default(false)->after('error_count');
        });
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->dropColumn('convert_to_opportunity');
        });
    }
};
