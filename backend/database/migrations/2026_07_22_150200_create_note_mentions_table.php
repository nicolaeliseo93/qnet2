<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collaborative notes module (spec 0052, D-10/D-12): the @mention pivot
 * between a note and a mentioned user. UNIQUE [note_id, user_id] enforces
 * "a repeated token for the same user counts as ONE mention" at the schema
 * level (AC-052), not just in the service. `user_id` is indexed for the
 * future "mentions for me" lookup (out of scope in this phase, D-9/out).
 * Both FKs cascadeOnDelete: mentions are pure metadata of their note/user,
 * never meaningful once either side is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('notes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['note_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_mentions');
    }
};
