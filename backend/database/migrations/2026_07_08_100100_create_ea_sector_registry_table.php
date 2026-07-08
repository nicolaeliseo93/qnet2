<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the EA sector <-> registry relation (spec 0020, "Settore EA /
 * Competenze"). Both sides cascade: deleting either the sector or the
 * registry drops the membership row, no orphaned pivot data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ea_sector_registry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ea_sector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registry_id')->constrained()->cascadeOnDelete();

            $table->unique(['ea_sector_id', 'registry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_sector_registry');
    }
};
