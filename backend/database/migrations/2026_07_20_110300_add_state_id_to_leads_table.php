<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regione (spec 0047, D1): `leads.state_id` is derived server-side from the
 * lead's sede (`operational_site->stateId`, itself the site's
 * `primaryAddress->state_id`) at create/update time — never user-editable —
 * so it stays nullable and `nullOnDelete` (a Region being removed should not
 * block/cascade the lead itself, mirroring how `source_id`/`operator_id` are
 * FK-optional on this same table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->after('operational_site_id')->constrained('states')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_id');
        });
    }
};
