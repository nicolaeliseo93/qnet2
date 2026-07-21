<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunity Workflow Configurator integration (spec 0047): `state_id`
 * (Regione, D1) is inherited from the originating Lead at conversion
 * (LeadOpportunityDefaultsResolver) or editable on a standalone Opportunity;
 * `opportunity_workflow_status_id` is the currently resolved working-state
 * row (App\Models\OpportunityWorkflowStatus — the NEW workflow dimension,
 * distinct from `opportunity_status_id`/pipeline), always written by
 * OpportunityWorkflowResolver, never directly user-mass-assignable. Both
 * `nullOnDelete`: losing the referenced Region/status row must not cascade
 * or block the Opportunity itself — the resolver re-derives it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('opportunity_workflow_status_id')->nullable()->constrained('opportunity_workflow_statuses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_id');
            $table->dropConstrainedForeignId('opportunity_workflow_status_id');
        });
    }
};
