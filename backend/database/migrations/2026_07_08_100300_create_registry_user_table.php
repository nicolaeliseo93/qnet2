<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the registry <-> internal manager (user) relation (spec 0020,
 * "Gestori interni"). Both sides cascade: deleting either the registry or
 * the user drops the membership row, no orphaned pivot data. The MAX 4
 * managers rule is validation-layer only (see StoreRegistryRequest), not a
 * DB constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registry_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['registry_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registry_user');
    }
};
