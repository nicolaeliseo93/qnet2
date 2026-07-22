<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collaborative notes module (spec 0052, D-9): an AGNOSTIC, polymorphic
 * discussion thread attachable to any entity via `notable_type`/`notable_id`
 * (the DB-side vocabulary — the alias from the global morph map, e.g.
 * "opportunity"; the API-side `entity_type`/`entity_id` slug translation
 * lives in config/notes.php, never here).
 *
 * `parent_id` is a nullable self-reference (D-7): null = root note, set = a
 * flat reply to that root (single-level thread, enforced server-side, not by
 * the schema). cascadeOnDelete so a hard-deleted root (never happens via the
 * API — deletes are soft, D-8) would not orphan its replies.
 *
 * `user_id` is the author, restrictOnDelete: a user with authored notes
 * cannot be hard-deleted without first reassigning/removing their notes —
 * the note itself must never lose its author silently.
 *
 * `edited_at` is set only when the body is modified after creation (D-8).
 * softDeletes: deletion is tracked, never physical (D-8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('notable');
            $table->foreignId('parent_id')->nullable()->constrained('notes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->dateTime('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['notable_type', 'notable_id', 'created_at']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
