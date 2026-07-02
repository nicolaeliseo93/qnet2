<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes cities.name for the geo cascade city search (ADR 0010).
 *
 * GET /api/cities filters by state_id (already indexed by its foreign key) and
 * an optional `name LIKE` search; this index supports that prefix search and the
 * `order by name`. The parent-filter columns (states.country_id, cities.state_id,
 * cities.country_id) are already indexed by their foreign keys, so only the
 * search column is added here — no FK index is duplicated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->index('name', 'cities_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex('cities_name_index');
        });
    }
};
