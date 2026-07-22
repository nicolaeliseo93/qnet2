<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two descriptive columns of a working status (spec 0047 amendment):
 * `description` — the free-text explanation shown next to the status in the
 * configurator, in the working-status select and as the table badge's
 * tooltip; `requires_note` — the flag marking a status as one that requires
 * an explanatory note. The flag is CONFIGURATION ONLY at this stage: nothing
 * enforces a note when the status is applied (deliberate scope decision), it
 * only drives the "note required" marker in the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_workflow_statuses', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('color');
            $table->boolean('requires_note')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_workflow_statuses', function (Blueprint $table) {
            $table->dropColumn(['description', 'requires_note']);
        });
    }
};
