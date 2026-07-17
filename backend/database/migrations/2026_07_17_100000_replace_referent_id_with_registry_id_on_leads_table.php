<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0041, D-1: a Lead's contact is corrected from a Referent to an
 * Anagrafica (Registry) — SUBSTITUTION, not addition. D-2: dev/demo data is
 * ricreabile, so this drops `referent_id` outright instead of backfilling a
 * `registry_id` from it. `restrictOnDelete` mirrors the original FK (BR-2:
 * RegistryService::delete() now carries the domain-layer 409 guard).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['referent_id']);
            $table->dropColumn('referent_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('registry_id')->after('id')->constrained('registries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['registry_id']);
            $table->dropColumn('registry_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('referent_id')->after('id')->constrained('referents')->restrictOnDelete();
        });
    }
};
