<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow status lookup entity (spec 0047): the "stato di lavorazione" pick
 * list for an Opportunity — a NEW dimension, distinct from
 * `opportunity_statuses` (the sales pipeline: Nuova/Chiusa con successo/
 * Persa, spec 0043). Each row belongs to one workflow
 * (`opportunity_workflow_id`) OR to the GLOBAL default set when the column is
 * NULL (the fallback every non-matching Opportunity resolves to, AC-010),
 * seeded below. Mirrors `opportunity_statuses`' system-row shape
 * (`system_key`/`group`) but PER-SET: every set (a workflow's own, or the
 * global one) carries its own pinned 'open'/'closed' system rows (AC-004).
 *
 * PERCHE (caveat, task-mandated): MySQL/SQLite treat NULL as DISTINCT in a
 * UNIQUE index, so unique(['opportunity_workflow_id','name']) and
 * unique(['opportunity_workflow_id','system_key']) do NOT enforce uniqueness
 * across the global set's rows (opportunity_workflow_id IS NULL) at the DB
 * layer — every such row's tuple is considered distinct from every other
 * regardless of `name`/`system_key`. Per-workflow rows (non-null id) ARE
 * correctly enforced by these indexes. The global set's name/system_key
 * uniqueness is therefore an APP-LAYER invariant (the service that syncs the
 * global set in a later lane), not a DB-enforced one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_workflow_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_workflow_id')->nullable()->constrained('opportunity_workflows')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('color', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('system_key', 16)->nullable();
            $table->string('group', 16)->default('open');
            $table->timestamps();

            $table->unique(['opportunity_workflow_id', 'name'], 'ow_statuses_workflow_name_unique');
            $table->unique(['opportunity_workflow_id', 'system_key'], 'ow_statuses_workflow_system_key_unique');
        });

        $this->seedGlobalDefaultSet();
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_workflow_statuses');
    }

    /**
     * Seeds the GLOBAL default set (opportunity_workflow_id null, spec 0047
     * scope item/AC-005): every Opportunity matching no active workflow
     * falls back to this set. `updateOrInsert` keyed on the (null workflow
     * id, system_key) pair for idempotency across repeated migrate:fresh runs
     * in tests.
     */
    private function seedGlobalDefaultSet(): void
    {
        $now = now();

        $rows = [
            ['name' => 'Aperta', 'color' => null, 'sort_order' => 0, 'system_key' => 'open', 'group' => 'open'],
            ['name' => 'Chiusa', 'color' => null, 'sort_order' => 10, 'system_key' => 'closed', 'group' => 'closed'],
        ];

        foreach ($rows as $row) {
            DB::table('opportunity_workflow_statuses')->updateOrInsert(
                ['opportunity_workflow_id' => null, 'system_key' => $row['system_key']],
                $row + ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }
};
