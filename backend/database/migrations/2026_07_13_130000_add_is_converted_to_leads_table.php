<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the lead-conversion flag (spec 0026, D-1): a single boolean, default
 * false, indexed (filtered/aggregated on the projects card grid and the
 * leads table). No timestamp column — activity log already records who/when
 * flipped it. Conversion rate is always derived, never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('is_converted')->default(false)->index()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('is_converted');
        });
    }
};
