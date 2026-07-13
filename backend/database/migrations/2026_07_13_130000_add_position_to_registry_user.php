<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives each `registry_user` membership a 1-based `position` = its "G.A. n"
 * slot number within the registry (spec: static manager ranking inherited by
 * future modules like Opportunities). The number is tied to the SLOT, not the
 * user: removing a manager frees its position (a gap = an empty slot that the
 * UI keeps), so the surviving managers never renumber. Positions are unique per
 * registry; gaps are allowed by design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registry_user', function (Blueprint $table): void {
            $table->unsignedInteger('position')->default(1)->after('user_id');
        });

        // Backfill sequential 1-based positions per registry (existing rows had
        // no order); done before the unique index so the default 1s don't clash.
        $counters = [];
        DB::table('registry_user')->orderBy('registry_id')->orderBy('id')
            ->get()
            ->each(function (object $row) use (&$counters): void {
                $position = ($counters[$row->registry_id] ?? 0) + 1;
                $counters[$row->registry_id] = $position;
                DB::table('registry_user')->where('id', $row->id)->update(['position' => $position]);
            });

        Schema::table('registry_user', function (Blueprint $table): void {
            $table->unique(['registry_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('registry_user', function (Blueprint $table): void {
            $table->dropUnique(['registry_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
