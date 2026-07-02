<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user table column preferences (ADR-0004).
 *
 * One row per (user, table domain) holding a SPARSE delta over the table's PHP
 * default schema — only the presentation properties the user changed
 * (visible / width / order), never a full copy. The PHP TableDefinition stays
 * the single source of truth, so adding/removing/renaming a column needs no data
 * migration here.
 *
 * Domain-agnostic: the same table serves every domain (users, products, …).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_table_preferences', function (Blueprint $table) {
            $table->id();

            // Self-scoped: a preference always belongs to exactly one user and is
            // removed with them. The endpoints never read user_id from the client.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The TableRegistry domain key (e.g. "users"); validated against
            // config/tables.php before anything reaches this table.
            $table->string('domain');

            // Sparse delta keyed by column id, e.g.
            // {"name":{"width":400,"order":2},"email":{"visible":false}}.
            $table->json('preferences');

            $table->timestamps();

            // One layout per user per table → the write is an idempotent upsert,
            // and the read is a single indexed lookup.
            $table->unique(['user_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_table_preferences');
    }
};
