<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campi Extra on a Lead (spec 0033): a raw JSON key/value store for
 * columns that do not map to a declared field, whether typed manually on
 * the Lead form or mapped to `__extra__` during the import wizard
 * (LeadsImportDefinition). Deliberately NOT the Universal Custom Fields
 * system (spec 0021, out of scope here) — free-form pairs only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->json('extra_fields')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('extra_fields');
        });
    }
};
