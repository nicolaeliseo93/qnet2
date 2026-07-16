<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the opportunity <-> internal manager (user) relation (spec 0040,
 * "Gestori Account"), mirroring `registry_user` (2026_07_08_100300 +
 * 2026_07_13_130000): both sides cascade — deleting either the opportunity or
 * the user drops the membership row, never a blocking guard (BR-3 explicitly
 * excludes this pivot). `position` is the 1-based "G.A. n" slot, tied to the
 * SLOT not the user (removing a manager frees its position, a gap the UI
 * keeps). The MAX 4 managers rule is validation-layer only (see
 * StoreOpportunityRequest), not a DB constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->unique(['opportunity_id', 'user_id']);
            $table->unique(['opportunity_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_user');
    }
};
