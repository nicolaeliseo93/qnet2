<?php

use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-043 (write-side only) — LEAD DECISION: the module exposes NO separately-
// gated activity endpoint. The generic ActivityLogController resolves its
// Policy by MODEL CLASS (Opportunity), so a `request-management` resource key
// in config/activity-log.php would have been gated by
// `opportunities.viewActivity`/`opportunities.view`, never
// `request-management.viewActivity` — misleading, removed. Operational
// tracking stays: RequestManagementService::updateWork() writes an explicit
// activity() entry directly on the Opportunity for every operative PATCH
// (workflow status + attribute_values), reachable only through the
// Opportunity's own activity-log resource key.
// ---------------------------------------------------------------------------

if (! function_exists('requestManagementActivityUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementActivityUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

it('PATCH /api/request-management/{id} writes exactly one activity entry on the Opportunity (AC-043)', function () {
    $actor = requestManagementActivityUserWith(['viewAny', 'view', 'update', 'viewAll']);
    $opportunity = Opportunity::factory()->create();
    $target = OpportunityWorkflowStatus::query()
        ->whereNull('opportunity_workflow_id')
        ->where('system_key', 'closed_won')
        ->sole();

    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => $target->id,
    ])->assertOk();

    $activities = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->get();

    expect($activities)->toHaveCount(1);
    expect($activities->first()->causer_id)->toBe($actor->id);
    expect($activities->first()->properties->get('attributes'))
        ->toMatchArray(['opportunity_workflow_status_id' => $target->id]);
});

it('the written activity is readable only via the opportunities resource key, never request-management', function () {
    $actor = requestManagementActivityUserWith(['viewAny', 'view', 'update', 'viewAll']);
    Permission::findOrCreate('opportunities.viewActivity');
    Permission::findOrCreate('opportunities.view');
    $actor->givePermissionTo(['opportunities.viewActivity', 'opportunities.view']);

    $opportunity = Opportunity::factory()->create();
    $target = OpportunityWorkflowStatus::query()
        ->whereNull('opportunity_workflow_id')
        ->where('system_key', 'closed_won')
        ->sole();

    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => $target->id,
    ])->assertOk();

    $this->getJson("/api/activity-log/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.changes.0.field', 'opportunity_workflow_status_id');

    // The `request-management` resource key is no longer registered — the
    // module has no dedicated, separately-gated activity surface.
    $this->getJson("/api/activity-log/request-management/{$opportunity->id}")
        ->assertNotFound();
});
