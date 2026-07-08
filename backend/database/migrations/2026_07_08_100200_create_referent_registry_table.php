<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the referent <-> registry relation (spec 0020, "Referenti per
 * azienda"). Both sides cascade: deleting either the referent or the
 * registry drops the membership row, no orphaned pivot data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referent_registry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registry_id')->constrained()->cascadeOnDelete();

            $table->unique(['referent_id', 'registry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referent_registry');
    }
};
