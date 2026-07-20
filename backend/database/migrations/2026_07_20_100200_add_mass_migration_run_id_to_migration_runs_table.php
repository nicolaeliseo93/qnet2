<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a per-source MigrationRun to its parent "Import all" run (spec 0046).
 * Nullable: a single-source run (spec 0013) has no parent. nullOnDelete keeps
 * the child rows if the aggregate is ever removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('migration_runs', function (Blueprint $table) {
            $table->foreignId('mass_migration_run_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('migration_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mass_migration_run_id');
        });
    }
};
