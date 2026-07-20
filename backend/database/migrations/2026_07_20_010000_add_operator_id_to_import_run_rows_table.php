<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-row Operator override (spec 0045): lets the reviewer pin a different
 * operator on ONE staged row, overriding the run's global `operator_id`
 * (LeadImportFieldCatalog global config) just for that lead. Nullable so
 * every existing/staged row defaults to the run-level operator; nullOnDelete
 * so a removed user never blocks deleting/staging rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table) {
            $table->foreignId('operator_id')->nullable()->after('resolution')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropColumn('operator_id');
        });
    }
};
