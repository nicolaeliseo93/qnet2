<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-row Operational Site override (mirrors the Operator override above):
 * lets the reviewer pin a different operational site on ONE staged row,
 * overriding the run's global `operational_site_id` (LeadImportFieldCatalog
 * global config) just for that lead. Nullable so every existing/staged row
 * defaults to the run-level site; nullOnDelete so a removed site never
 * blocks deleting/staging rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table) {
            $table->foreignId('operational_site_id')->nullable()->after('operator_id')->constrained('operational_sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table) {
            $table->dropForeign(['operational_site_id']);
            $table->dropColumn('operational_site_id');
        });
    }
};
