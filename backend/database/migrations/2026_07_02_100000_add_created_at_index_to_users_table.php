<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes users.created_at (spec 0004 — Excel-like table filters).
 *
 * `created_at` is the users table's default sort column (UsersTableDefinition
 * ::defaultSort, DESC) and a filterable `date` column (equals/range via
 * TableService::applyDateFilter); every GET /rows request touches it. `name`
 * and `email` are already indexed (email is unique, name has its own index
 * added in add_name_index_to_users_table), so this fills the last gap for the
 * SSRM listing's ORDER BY + WHERE. Not composite: the default sort has no
 * secondary column, and pagination stays offset-based (no keyset column pair
 * to cover) at this iteration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
