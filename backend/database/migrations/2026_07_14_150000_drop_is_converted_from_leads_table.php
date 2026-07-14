<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the lead-conversion flag (decision on top of spec 0026): the metric
 * and every derived stat are removed with it. Reversible: `down()` recreates
 * the column exactly as
 * `2026_07_13_130000_add_is_converted_to_leads_table` first defined it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['is_converted']);
            $table->dropColumn('is_converted');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->boolean('is_converted')->default(false)->index()->after('notes');
        });
    }
};
