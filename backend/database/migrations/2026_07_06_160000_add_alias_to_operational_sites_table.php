<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the site's own free-text `alias` — the legacy system exposes the site
 * label in its `comune` field (e.g. "FRATTAMAGGIORE 1 (HQ)"), which is a name,
 * not a real city. The migration stores that raw string here verbatim while
 * still best-effort resolving the actual comune onto the address (spec 0011 —
 * the site otherwise IS its address; this is its single own column besides the
 * timestamps/old_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_sites', function (Blueprint $table): void {
            $table->string('alias')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('operational_sites', function (Blueprint $table): void {
            $table->dropColumn('alias');
        });
    }
};
