<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0056: adds `operational_site_id` back onto `opportunities` as a plain
 * OPTIONAL FK (user directive 2026-07-23 — superseding the 2026-07-17
 * removal for THIS one column only; `company_id`/`company_site_id` stay
 * removed). Mirrors 2026_07_21_010000_add_operational_site_id_to_projects_
 * and_campaigns_tables.php verbatim, except `after('source_id')`: the
 * precedent's `after('city_id')` targets a column `opportunities` does not
 * have. `nullOnDelete` is a deliberate deviation from this table's other FKs
 * (restrictOnDelete, BR-3): the field is optional, so losing the referenced
 * site clears it rather than blocking the site's deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('operational_site_id')->nullable()->after('source_id')->constrained('operational_sites')->nullOnDelete();

            $table->index('operational_site_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropForeign(['operational_site_id']);
            $table->dropIndex(['operational_site_id']);
            $table->dropColumn('operational_site_id');
        });
    }
};
