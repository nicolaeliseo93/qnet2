<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic per-table CSV import run (spec 0012): one row per upload, tracking
 * the two-phase (validate dry-run -> confirm commit) flow's status, counts,
 * bounded preview and the downloadable errors report.
 *
 * `resource` is the `{domain}` key (App\Imports\ImportRegistry /
 * config/imports.php) the run was started against — a plain string, NOT a
 * foreign key, mirroring the generic Tables framework's domain string so the
 * import engine never couples to a specific resource's schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('resource')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->nullable();
            $table->string('error_report_path')->nullable();
            $table->json('preview')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
