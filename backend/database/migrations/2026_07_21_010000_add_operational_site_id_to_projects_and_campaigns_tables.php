<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sede (OperationalSite) inheritance cascade project -> campaign -> lead: adds
 * `operational_site_id` to `projects` and `campaigns`, mirroring the column
 * already present on `leads`. This is a PREFILL MODIFIABLE relation, not a
 * read-through: each level stores its own value, independently editable, with
 * no server-side inheritance/lock (the frontend pre-fills the child form from
 * the parent's current value, nothing more). Nullable + nullOnDelete, same
 * convention as the geo columns on these tables: losing the referenced site
 * must never delete the project/campaign it belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('operational_site_id')->nullable()->after('city_id')->constrained('operational_sites')->nullOnDelete();

            $table->index('operational_site_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('operational_site_id')->nullable()->after('city_id')->constrained('operational_sites')->nullOnDelete();

            $table->index('operational_site_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['operational_site_id']);
            $table->dropIndex(['operational_site_id']);
            $table->dropColumn('operational_site_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['operational_site_id']);
            $table->dropIndex(['operational_site_id']);
            $table->dropColumn('operational_site_id');
        });
    }
};
