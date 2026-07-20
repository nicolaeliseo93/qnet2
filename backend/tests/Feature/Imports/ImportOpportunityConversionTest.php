<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowResolution;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0045 — per-row Operator override (PATCH .../rows/{row}) and
 * auto-convert-to-Opportunity during lead import (POST .../confirm with
 * `convert_to_opportunity: true`, gated by ImportOpportunityConvertibility).
 */

/**
 * @param  array<int, string>  $abilities  `leads.*` abilities
 * @param  array<int, string>  $opportunityAbilities  `opportunities.*` abilities — required in
 *                                                    addition to `leads.import` to confirm with `convert_to_opportunity: true`
 */
function conversionActor(array $abilities, array $opportunityAbilities = []): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
        Permission::findOrCreate("opportunities.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    foreach ($opportunityAbilities as $ability) {
        $user->givePermissionTo("opportunities.{$ability}");
    }

    if (in_array('import', $abilities, true)) {
        grantImportRunsPermissions($user, ['update']);
    }

    return $user;
}

/**
 * A campaign whose business function/product category derive a non-empty
 * Opportunity product line, plus an operational site and a global operator
 * — the 3 ingredients ImportOpportunityConvertibility requires for a run to
 * be ready. Each blocker test starts from this and removes ONE ingredient.
 *
 * @return array{campaign: Campaign, operationalSite: OperationalSite, operator: User}
 */
function conversionReadyFixture(): array
{
    $businessFunction = BusinessFunction::factory()->create();
    $productCategory = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $campaign = Campaign::factory()->create([
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $productCategory->id,
    ]);

    return [
        'campaign' => $campaign,
        'operationalSite' => OperationalSite::factory()->create(),
        'operator' => User::factory()->create(),
    ];
}

// ---------------------------------------------------------------------------
// Per-row Operator override — PATCH /api/imports/leads/{importRun}/rows/{row}
// ---------------------------------------------------------------------------

it('sets the per-row operator override without touching status/mapped_values', function () {
    $actor = conversionActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'mapped_values' => ['first_name' => 'Mario', 'last_name' => 'Rossi'],
        'status' => ImportRowStatus::Valid,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['operator_id' => $operator->id])
        ->assertOk()
        ->assertJsonPath('data.row.operator_id', $operator->id)
        ->assertJsonPath('data.row.operator.id', $operator->id)
        ->assertJsonPath('data.row.operator.name', $operator->name)
        ->assertJsonPath('data.row.is_edited', true)
        ->assertJsonPath('data.row.status', 'valid');

    $row->refresh();
    expect($row->operator_id)->toBe($operator->id)
        ->and($row->mapped_values)->toBe(['first_name' => 'Mario', 'last_name' => 'Rossi']);
});

it('clears the per-row operator override with an explicit null', function () {
    $actor = conversionActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'operator_id' => $operator->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['operator_id' => null])
        ->assertOk()
        ->assertJsonPath('data.row.operator_id', null)
        ->assertJsonPath('data.row.operator', null);

    expect($row->fresh()->operator_id)->toBeNull();
});

it('403 without leads.import on the per-row operator override', function () {
    $actor = conversionActor([]);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['operator_id' => User::factory()->create()->id])
        ->assertForbidden();
});

it('422 when none of values/geo/operator_id is submitted', function () {
    $actor = conversionActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [])
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Confirm gate — POST /api/imports/leads/{importRun}/confirm, convert_blockers
// ---------------------------------------------------------------------------

it('422 with operational_site_missing when the run has no operational_site_id', function () {
    $actor = conversionActor(['import'], ['create']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        'global_config' => ['campaign_id' => $fixture['campaign']->id, 'operator_id' => $fixture['operator']->id],
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'status' => ImportRowStatus::Valid]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('convert_blockers.operational_site_missing', true)
        ->assertJsonPath('convert_blockers.campaign_missing_product_line', false)
        ->assertJsonPath('convert_blockers.rows_without_operator', []);

    expect($run->fresh()->status)->toBe(ImportStatus::Reviewing)
        ->and(Lead::query()->count())->toBe(0);
});

it('422 with campaign_missing_product_line when the campaign derives no product line', function () {
    $actor = conversionActor(['import'], ['create']);
    $campaign = Campaign::factory()->create(['business_function_id' => null, 'product_category_id' => null]);
    $operationalSite = OperationalSite::factory()->create();
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        'global_config' => [
            'campaign_id' => $campaign->id,
            'operational_site_id' => $operationalSite->id,
            'operator_id' => $operator->id,
        ],
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'status' => ImportRowStatus::Valid]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertStatus(422)
        ->assertJsonPath('convert_blockers.operational_site_missing', false)
        ->assertJsonPath('convert_blockers.campaign_missing_product_line', true)
        ->assertJsonPath('convert_blockers.rows_without_operator', []);
});

it('422 with rows_without_operator listing every creatable row with no effective operator', function () {
    $actor = conversionActor(['import'], ['create']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        // No global operator_id — every row must supply its own.
        'global_config' => ['campaign_id' => $fixture['campaign']->id, 'operational_site_id' => $fixture['operationalSite']->id],
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'operator_id' => User::factory()->create()->id,
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 3, 'status' => ImportRowStatus::Valid]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertStatus(422)
        ->assertJsonPath('convert_blockers.operational_site_missing', false)
        ->assertJsonPath('convert_blockers.campaign_missing_product_line', false)
        ->assertJsonPath('convert_blockers.rows_without_operator', [1, 3]);
});

it('403 with convert_to_opportunity:true when the actor lacks opportunities.create, even with leads.import', function () {
    // Regression (verifier finding): the confirm gate must enforce
    // `opportunities.create` server-side — the frontend `<Can>` gate alone
    // is not authorization (security.md §1).
    $actor = conversionActor(['import']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        'global_config' => [
            'campaign_id' => $fixture['campaign']->id,
            'operational_site_id' => $fixture['operationalSite']->id,
            'operator_id' => $fixture['operator']->id,
        ],
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Should', 'last_name' => 'NotConvert', 'email' => 'should-not-convert@example.com'],
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertForbidden();

    // The authorization check runs BEFORE confirmStaged(): the run never
    // transitions to processing, no job is dispatched, nothing is persisted.
    expect($run->fresh()->status)->toBe(ImportStatus::Reviewing)
        ->and($run->fresh()->convert_to_opportunity)->toBeFalse()
        ->and(Lead::query()->count())->toBe(0)
        ->and(Opportunity::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Happy path — convert_to_opportunity true/false
// ---------------------------------------------------------------------------

it('converts every CREATE-branch row when ready; the UPDATE-branch row never converts', function () {
    $actor = conversionActor(['import'], ['create']);
    $fixture = conversionReadyFixture();
    $rowOperator = User::factory()->create();

    // A Registry+Lead already in the SAME campaign — the row resolving to it
    // (Manual, resolution=update) takes the UPDATE branch.
    $existingRegistry = Registry::factory()->create();
    $existingCard = PersonalData::factory()->individual()->for($existingRegistry, 'personable')->create();
    Contact::factory()->email()->for($existingCard, 'contactable')->create(['value' => 'existing@example.com']);
    $existingLead = Lead::factory()->create(['registry_id' => $existingRegistry->id, 'campaign_id' => $fixture['campaign']->id]);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::Manual->value,
        'global_config' => [
            'campaign_id' => $fixture['campaign']->id,
            'operational_site_id' => $fixture['operationalSite']->id,
            'operator_id' => $fixture['operator']->id,
        ],
    ]);

    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Global', 'last_name' => 'Operator', 'email' => 'global-op@example.com'],
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'Own', 'last_name' => 'Operator', 'email' => 'own-op@example.com'],
        'operator_id' => $rowOperator->id,
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 3,
        'status' => ImportRowStatus::Duplicate,
        'mapped_values' => ['first_name' => 'Existing', 'last_name' => 'Person', 'email' => 'existing@example.com'],
        'duplicate_of_id' => $existingRegistry->id,
        'resolution' => ImportRowResolution::Update,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm", ['convert_to_opportunity' => true])
        ->assertOk()
        ->assertJsonPath('data.import_run.status', 'completed');

    expect($run->fresh()->convert_to_opportunity)->toBeTrue();

    $globalOperatorLead = Lead::query()->where('operator_id', $fixture['operator']->id)->where('id', '!=', $existingLead->id)->firstOrFail();
    $rowOperatorLead = Lead::query()->where('operator_id', $rowOperator->id)->firstOrFail();

    expect(Opportunity::where('lead_id', $globalOperatorLead->id)->count())->toBe(1)
        ->and(Opportunity::where('lead_id', $rowOperatorLead->id)->count())->toBe(1)
        ->and(Opportunity::where('lead_id', $existingLead->id)->exists())->toBeFalse();
});

it('convert_to_opportunity absent never requires opportunities.create — plain import stays accessible with only leads.import', function () {
    // The actor deliberately has NO `opportunities.create` here: a plain
    // (non-converting) confirm must never require it.
    $actor = conversionActor(['import']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::CreateNew->value,
        'global_config' => [
            'campaign_id' => $fixture['campaign']->id,
            'operational_site_id' => $fixture['operationalSite']->id,
            'operator_id' => $fixture['operator']->id,
        ],
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'status' => ImportRowStatus::Valid,
        'mapped_values' => ['first_name' => 'No', 'last_name' => 'Conversion', 'email' => 'no-conversion@example.com'],
    ]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.import_run.status', 'completed');

    expect($run->fresh()->convert_to_opportunity)->toBeFalse()
        ->and(Lead::query()->count())->toBe(1)
        ->and(Opportunity::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// GET /api/imports/leads/{importRun}/summary — conversion_readiness
// ---------------------------------------------------------------------------

it('summary reports conversion_readiness computed from the run/rows', function () {
    $actor = conversionActor(['import']);
    $fixture = conversionReadyFixture();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'dedup_strategy' => ImportDedupMode::Manual->value,
        // No global operator_id: only rows with their OWN override count.
        'global_config' => ['campaign_id' => $fixture['campaign']->id, 'operational_site_id' => $fixture['operationalSite']->id],
    ]);

    // Creatable, no operator.
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid]);
    // Creatable, has its own operator.
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 2,
        'status' => ImportRowStatus::Valid,
        'operator_id' => User::factory()->create()->id,
    ]);
    // Creatable (duplicate resolved to `create`), no operator.
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 3,
        'status' => ImportRowStatus::Duplicate,
        'duplicate_of_id' => 999,
        'resolution' => ImportRowResolution::Create,
    ]);
    // NOT creatable: duplicate resolved to `update`.
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 4,
        'status' => ImportRowStatus::Duplicate,
        'duplicate_of_id' => 999,
        'resolution' => ImportRowResolution::Update,
    ]);
    // NOT creatable: error/skipped rows.
    ImportRunRow::factory()->error()->create(['import_run_id' => $run->id, 'row_number' => 5]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 6, 'status' => ImportRowStatus::Skipped]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}/summary")
        ->assertOk()
        ->assertJsonPath('data.summary.conversion_readiness.operational_site_set', true)
        ->assertJsonPath('data.summary.conversion_readiness.campaign_derives_product_line', true)
        ->assertJsonPath('data.summary.conversion_readiness.creatable_rows', 3)
        ->assertJsonPath('data.summary.conversion_readiness.rows_without_operator', 2);
});
