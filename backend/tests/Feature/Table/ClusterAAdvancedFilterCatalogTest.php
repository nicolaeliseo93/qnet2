<?php

use App\Enums\AdvancedFilterType;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\PipelineStatus;
use App\Models\Project;
use App\Models\User;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Grants the standard CRUD abilities for `$domain` (BasePolicy convention:
 * "{domain}.{ability}") to a fresh user, restricted to `$abilities`. Shared
 * across every domain in this file — all 5 are plain BasePolicy resources.
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('userWithDomainAbilities')) {
    function userWithDomainAbilities(string $domain, array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("{$domain}.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("{$domain}.{$ability}");
        }

        return $user;
    }
}

/**
 * Schema-valid assertion mirroring AC-002: unique `name`, `type` a real enum
 * case, `order` an int, the 3 flags booleans, `width` in the allowed set, and
 * `source` present (XOR options/enumKey) exactly when `type` is `relation`.
 *
 * @param  array<int, array<string, mixed>>  $descriptors
 */
if (! function_exists('assertAdvancedFilterCatalogSchemaValid')) {
    function assertAdvancedFilterCatalogSchemaValid(array $descriptors): void
    {
        expect($descriptors)->not->toBeEmpty();

        $names = array_column($descriptors, 'name');
        expect($names)->toBe(array_unique($names));

        foreach ($descriptors as $descriptor) {
            expect($descriptor['type'])->toBeInstanceOf(AdvancedFilterType::class)
                ->and($descriptor['order'])->toBeInt()
                ->and($descriptor['required'])->toBeBool()
                ->and($descriptor['visible'])->toBeBool()
                ->and($descriptor['multiple'])->toBeBool()
                ->and($descriptor['width'])->toBeIn(['sm', 'md', 'lg', 'full']);

            $carriesSource = array_key_exists('source', $descriptor);
            $carriesOptionsOrEnum = array_key_exists('options', $descriptor) || array_key_exists('enumKey', $descriptor);

            if ($descriptor['type'] === AdvancedFilterType::Relation) {
                expect($carriesSource)->toBeTrue()->and($carriesOptionsOrEnum)->toBeFalse();
            } else {
                expect($carriesSource && $carriesOptionsOrEnum)->toBeFalse();
            }
        }
    }
}

it('exposes a non-empty, schema-valid advancedFilters() catalog for each cluster-A domain (AC-002)', function (string $domain) {
    $definition = app(TableRegistry::class)->resolve($domain);

    assertAdvancedFilterCatalogSchemaValid($definition->advancedFilters());
})->with([
    'leads', 'campaigns', 'projects', 'pipeline-statuses', 'lead-statuses',
]);

it('filters leads by lead_status via the generic relation-by-id default (AC-003)', function () {
    Sanctum::actingAs(userWithDomainAbilities('leads', ['viewAny']));

    $won = LeadStatus::factory()->create();
    $lost = LeadStatus::factory()->create();
    Lead::factory()->count(2)->create(['lead_status_id' => $won->id]);
    Lead::factory()->create(['lead_status_id' => $lost->id]);

    $response = $this->postJson('/api/tables/leads/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['lead_status' => [$won->id]],
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(2)
        ->and(collect($response->json('items'))->pluck('lead_status.id')->unique()->all())->toBe([$won->id]);
});

it('filters campaigns by pipeline_status through the BR-2 own-or-project override (AC-007)', function () {
    Sanctum::actingAs(userWithDomainAbilities('campaigns', ['viewAny']));

    $ownStatus = PipelineStatus::factory()->create();
    $projectStatus = PipelineStatus::factory()->create();

    $standalone = Campaign::factory()->create(['pipeline_status_id' => $ownStatus->id]);
    $project = Project::factory()->create(['pipeline_status_id' => $projectStatus->id]);
    $linked = Campaign::factory()->forProject($project)->create();

    $response = $this->postJson('/api/tables/campaigns/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['pipeline_status' => [$projectStatus->id]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id')->all();
    expect($ids)->toBe([$linked->id])->not->toContain($standalone->id);
});

it('filters projects by pipeline_status via the generic relation-by-id default', function () {
    Sanctum::actingAs(userWithDomainAbilities('projects', ['viewAny']));

    $active = PipelineStatus::factory()->create();
    $closed = PipelineStatus::factory()->create();
    $matching = Project::factory()->create(['pipeline_status_id' => $active->id]);
    Project::factory()->create(['pipeline_status_id' => $closed->id]);

    $response = $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['pipeline_status' => [$active->id]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

it('filters pipeline-statuses by a free-text name match', function () {
    Sanctum::actingAs(userWithDomainAbilities('pipeline-statuses', ['viewAny']));

    PipelineStatus::factory()->create(['name' => 'Won']);
    PipelineStatus::factory()->create(['name' => 'Lost']);

    $response = $this->postJson('/api/tables/pipeline-statuses/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['name' => 'Wo'],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toBe(['Won']);
});

it('filters lead-statuses by a free-text name match', function () {
    Sanctum::actingAs(userWithDomainAbilities('lead-statuses', ['viewAny']));

    LeadStatus::factory()->create(['name' => 'Qualified']);
    LeadStatus::factory()->create(['name' => 'New']);

    $response = $this->postJson('/api/tables/lead-statuses/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['name' => 'Quali'],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toBe(['Qualified']);
});
