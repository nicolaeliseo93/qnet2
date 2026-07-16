<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-row duplicate resolution support (spec 0036): `duplicate_meta` records
 * the matched referent/lead once staging (or a review edit) detects a
 * duplicate — { referent_id, referent_name, lead_id, matched_on: string[] }
 * — and `resolution` ("skip"|"create"|"update") is the operator's per-row
 * choice, read back by the commit phase instead of falling back to the
 * global dedup strategy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table) {
            $table->json('duplicate_meta')->nullable()->after('duplicate_of_id');
            $table->string('resolution')->nullable()->after('duplicate_meta');
        });
    }

    public function down(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table) {
            $table->dropColumn(['duplicate_meta', 'resolution']);
        });
    }
};
