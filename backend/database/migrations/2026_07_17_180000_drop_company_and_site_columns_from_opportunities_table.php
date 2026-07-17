<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User directive 2026-07-17: `company_id`/`company_site_id`/
 * `operational_site_id` are REMOVED from `opportunities` entirely (no longer
 * mandatory fields, no longer relations) — the FK constraint is dropped
 * before its column (works on both MySQL and SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('company_site_id');
            $table->dropConstrainedForeignId('operational_site_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('company_id')->after('registry_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('company_site_id')->after('company_id')->constrained('company_sites')->restrictOnDelete();
            $table->foreignId('operational_site_id')->after('company_site_id')->constrained('operational_sites')->restrictOnDelete();
        });
    }
};
