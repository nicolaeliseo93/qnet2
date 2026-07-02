<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index users.name to bound the for-select search scan (GET /api/users/for-select
 * matches name OR email). email is already unique-indexed; name was not indexed.
 * A trailing-wildcard prefix match can use this index; the substring match used by
 * the typeahead cannot, but the index still narrows equality/prefix lookups and is
 * the right baseline before any future full-text move (see ADR 0011 Risks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
