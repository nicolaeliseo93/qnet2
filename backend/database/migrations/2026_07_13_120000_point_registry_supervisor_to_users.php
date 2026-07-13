<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-points `registries.supervisor_id` from `referents` to `users`: the
 * supervisor of a client/supplier relationship is an INTERNAL user (like the
 * `managers` pivot), not an external referent (commercial/reporter stay on
 * referents). Existing values reference referent ids, incompatible with the
 * new FK target, so they are cleared on both directions of the migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registries', function (Blueprint $table): void {
            $table->dropForeign(['supervisor_id']);
        });

        // Existing ids point to referents; they cannot satisfy a users FK.
        DB::table('registries')->update(['supervisor_id' => null]);

        Schema::table('registries', function (Blueprint $table): void {
            $table->foreign('supervisor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registries', function (Blueprint $table): void {
            $table->dropForeign(['supervisor_id']);
        });

        DB::table('registries')->update(['supervisor_id' => null]);

        Schema::table('registries', function (Blueprint $table): void {
            $table->foreign('supervisor_id')->references('id')->on('referents')->nullOnDelete();
        });
    }
};
