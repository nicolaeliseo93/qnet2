<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced filters (spec 0032), a second, backend-driven filter level above
 * the AG Grid column filters. A separate `advanced_filters` JSON column on
 * both filter-state tables keeps the existing `filters` column (AG Grid
 * filterModel) untouched in shape, so the two concepts persist independently
 * while sharing the same row (per-user applied state / named saved view).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_table_filters', function (Blueprint $table) {
            $table->json('advanced_filters')->nullable()->after('filters');
        });

        Schema::table('table_filter_views', function (Blueprint $table) {
            $table->json('advanced_filters')->nullable()->after('filters');
        });
    }

    public function down(): void
    {
        Schema::table('user_table_filters', function (Blueprint $table) {
            $table->dropColumn('advanced_filters');
        });

        Schema::table('table_filter_views', function (Blueprint $table) {
            $table->dropColumn('advanced_filters');
        });
    }
};
