<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic per-table export run (spec 0014): one row per export request,
 * tracking the async (queued) generation flow's status and the downloadable
 * generated file.
 *
 * `resource` is the `{domain}` key (App\Tables\TableRegistry /
 * config/tables.php) the run was started against — a plain string, NOT a
 * foreign key, mirroring `import_runs.resource` so the export engine never
 * couples to a specific resource's schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_runs', function (Blueprint $table) {
            $table->id();
            $table->string('resource')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('format');
            $table->string('original_filename');
            // Frozen grid state at request time: { columns:[{colId,header}],
            // sortModel, filterModel, search } — the single source of truth
            // GenerateExportJob reads to build the query and the file header.
            $table->json('state');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_runs');
    }
};
