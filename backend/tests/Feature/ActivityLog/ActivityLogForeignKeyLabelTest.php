<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * FK-label extension of spec 0034 (client-reported: `referent_id: — → 206`
 * instead of the referent's name; noise rows like
 * `operational_site_id: — → —` for fields left null at creation). Mirrors the
 * other ActivityLog test files' self-contained `activityLogActor` helper.
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('fkLabelActor')) {
    function fkLabelActor(string $resource, array $abilities): User
    {
        foreach (['viewAny', 'view', 'viewActivity'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo("{$resource}.{$ability}");
        }

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// created: populated FK fields get a label, null-null fields are dropped
// ---------------------------------------------------------------------------

it('a created lead resolves FK labels for populated fields and drops null-null noise', function () {
    $actor = fkLabelActor('leads', ['view', 'viewActivity']);
    $referent = Referent::factory()->create(['name' => 'Jane Referent']);
    $campaign = Campaign::factory()->create(['name' => 'Spring Campaign']);
    $leadStatus = LeadStatus::factory()->create(['name' => 'New']);
    Sanctum::actingAs($actor);

    // operational_site_id/source_id/operator_id stay null (LeadFactory default),
    // exactly the customer-reported noise ("operational_site_id: — → —").
    $lead = Lead::factory()->create([
        'referent_id' => $referent->id,
        'campaign_id' => $campaign->id,
        'lead_status_id' => $leadStatus->id,
        'notes' => 'Inbound call',
    ]);

    $items = collect($this->getJson("/api/activity-log/leads/{$lead->id}")->assertOk()->json('data.items'));
    $created = $items->firstWhere('event', 'created');
    $byField = collect($created['changes'])->keyBy('field');

    expect($byField->keys()->all())->not->toContain('operational_site_id', 'source_id', 'operator_id');

    expect($byField['referent_id']['old_display'])->toBeNull()
        ->and($byField['referent_id']['new_display'])->toBe('Jane Referent')
        ->and($byField['campaign_id']['new_display'])->toBe('Spring Campaign')
        ->and($byField['lead_status_id']['new_display'])->toBe('New');

    // A non-FK field never gets a display, FK or not.
    expect($byField['notes']['old_display'])->toBeNull()
        ->and($byField['notes']['new_display'])->toBeNull();
});

// ---------------------------------------------------------------------------
// updated: both sides of an FK change carry their label
// ---------------------------------------------------------------------------

it('an updated FK field resolves both old_display and new_display', function () {
    $actor = fkLabelActor('leads', ['view', 'viewActivity']);
    $referentA = Referent::factory()->create(['name' => 'Referent A']);
    $referentB = Referent::factory()->create(['name' => 'Referent B']);
    $lead = Lead::factory()->create(['referent_id' => $referentA->id]);
    Sanctum::actingAs($actor);

    $lead->update(['referent_id' => $referentB->id]);

    $items = collect($this->getJson("/api/activity-log/leads/{$lead->id}")->assertOk()->json('data.items'));
    $updated = $items->firstWhere('event', 'updated');
    $change = collect($updated['changes'])->firstWhere('field', 'referent_id');

    expect($change['old_value'])->toBe($referentA->id)
        ->and($change['new_value'])->toBe($referentB->id)
        ->and($change['old_display'])->toBe('Referent A')
        ->and($change['new_display'])->toBe('Referent B');
});

// ---------------------------------------------------------------------------
// FK target since deleted: display falls back to null, raw id is untouched
// ---------------------------------------------------------------------------

it('an FK pointing to a since-deleted record resolves to a null display, keeping the raw id', function () {
    $actor = fkLabelActor('business-functions', ['view', 'viewActivity']);
    $manager = User::factory()->create(['name' => 'Former Manager']);
    $function = BusinessFunction::factory()->create(['manager_id' => $manager->id]);
    Sanctum::actingAs($actor);

    $before = collect($this->getJson("/api/activity-log/business-functions/{$function->id}")->assertOk()->json('data.items'));
    $beforeChange = collect($before->firstWhere('event', 'created')['changes'])->firstWhere('field', 'manager_id');
    expect($beforeChange['new_display'])->toBe('Former Manager');

    $manager->delete(); // business_functions.manager_id is nullOnDelete: allowed, no FK violation

    $after = collect($this->getJson("/api/activity-log/business-functions/{$function->id}")->assertOk()->json('data.items'));
    $afterChange = collect($after->firstWhere('event', 'created')['changes'])->firstWhere('field', 'manager_id');

    expect($afterChange['new_value'])->toBe($manager->id)
        ->and($afterChange['new_display'])->toBeNull();
});

// ---------------------------------------------------------------------------
// No N+1: one label query per related class, regardless of entry/field count
// ---------------------------------------------------------------------------

it('resolves FK labels with one batched query per related class, never per row/field', function () {
    $actor = fkLabelActor('leads', ['view', 'viewActivity']);
    $referents = Referent::factory()->count(3)->create();
    $campaign = Campaign::factory()->create();
    $lead = Lead::factory()->create(['referent_id' => $referents[0]->id, 'campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    // 3 more referent_id updates -> 4 distinct referent ids across 4 activity
    // rows (1 created + 3 updated), all resolved by a SINGLE `referents` query.
    foreach ([1, 2, 0] as $index) {
        $lead->update(['referent_id' => $referents[$index]->id]);
    }

    DB::enableQueryLog();
    $this->getJson("/api/activity-log/leads/{$lead->id}")->assertOk();
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $labelQueries = fn (string $table): int => $queries->filter(
        fn (array $query): bool => str_contains($query['query'], "from \"{$table}\"") && str_contains($query['query'], ' in (')
    )->count();

    expect($labelQueries('referents'))->toBe(1)
        ->and($labelQueries('campaigns'))->toBe(1);
});
