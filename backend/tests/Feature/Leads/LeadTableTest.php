<?php

use App\Jobs\GenerateExportJob;
use App\Models\Campaign;
use App\Models\City;
use App\Models\ExportRun;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\OperationalSite;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-040 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/leads/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = leadUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/leads/columns')->assertForbidden();

    $actor = leadUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/leads/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('leads')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']]);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['referent', 'campaign', 'operational_site', 'source', 'operator', 'lead_status', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['operational_site']['sortable'])->toBeTrue()
        ->and($columns['operational_site']['filterType'])->toBe('set')
        ->and($columns['referent']['sortable'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-041 — a filter on the campaign column returns only that campaign's leads,
// applied to the ENTIRE dataset (not the page)
// ---------------------------------------------------------------------------

it('rows: a filter on the campaign column returns only that campaign\'s leads (AC-041)', function () {
    $actor = leadUserWith(['viewAny']);
    $campaign = Campaign::factory()->create(['name' => 'Match Campaign']);
    $otherCampaign = Campaign::factory()->create(['name' => 'Other Campaign']);
    $matching = Lead::factory()->create(['campaign_id' => $campaign->id]);
    Lead::factory()->create(['campaign_id' => $otherCampaign->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/leads/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['campaign' => ['filterType' => 'set', 'values' => ['Match Campaign']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matching->id]);
});

// ---------------------------------------------------------------------------
// AC-042 — sort on the operational_site (derived) column orders by the primary
// address' line1, via allow-list (no whereRaw/orderByRaw)
// ---------------------------------------------------------------------------

it('rows: sorting by operational_site orders leads by the site\'s primary address line1 (AC-042)', function () {
    $actor = leadUserWith(['viewAny']);

    $siteA = OperationalSite::factory()->create();
    $siteA->addresses()->create(['line1' => 'Alpha Street', 'is_primary' => true]);
    $siteB = OperationalSite::factory()->create();
    $siteB->addresses()->create(['line1' => 'Zulu Street', 'is_primary' => true]);

    $leadZulu = Lead::factory()->create(['operational_site_id' => $siteB->id]);
    $leadAlpha = Lead::factory()->create(['operational_site_id' => $siteA->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/leads/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'operational_site', 'sort' => 'asc']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$leadAlpha->id, $leadZulu->id]);
});

it('rows: an unknown sort column is rejected (allow-list, no raw SQL escape hatch)', function () {
    $actor = leadUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/leads/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'notes', 'sort' => 'asc']],
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-043 — operational_site row value is the composed label, or line1-only, or null
// ---------------------------------------------------------------------------

it('rows: operational_site is the composed "{line1} - {city}" label (AC-043)', function () {
    $actor = leadUserWith(['viewAny']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Roma 1', 'is_primary' => true, 'city_id' => City::factory()->create(['name' => 'Milano'])->id]);
    $lead = Lead::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $lead->id);

    expect($row['operational_site'])->toMatchArray(['id' => $site->id, 'label' => 'Via Roma 1 - Milano']);
});

it('rows: operational_site is line1-only when the address has no city', function () {
    $actor = leadUserWith(['viewAny']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Torino 2', 'is_primary' => true]);
    $lead = Lead::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $lead->id);

    expect($row['operational_site'])->toMatchArray(['id' => $site->id, 'label' => 'Via Torino 2']);
});

it('rows: operational_site is null when the lead has no site', function () {
    $actor = leadUserWith(['viewAny']);
    $lead = Lead::factory()->create(['operational_site_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $lead->id);

    expect($row['operational_site'])->toBeNull();
});

it('rows: referent/source/operator surface as {id, name} summaries', function () {
    $actor = leadUserWith(['viewAny']);
    $referent = Referent::factory()->create(['name' => 'Ada Contact']);
    $lead = Lead::factory()->create(['referent_id' => $referent->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $lead->id);

    expect($row['referent'])->toMatchArray(['id' => $referent->id, 'name' => 'Ada Contact']);
});

// ---------------------------------------------------------------------------
// spec 0029 AC-013 — lead_status row shape includes color (never through
// summarize(), unlike ProjectsTableDefinition's scolored-badge defect), and
// the derived column is filterable (set) and sortable via the allow-list.
// ---------------------------------------------------------------------------

it('rows: lead_status is {id, name, color}, mapped explicitly (AC-013)', function () {
    $actor = leadUserWith(['viewAny']);
    $status = LeadStatus::factory()->create(['name' => 'Qualified', 'color' => 'green']);
    $lead = Lead::factory()->create(['lead_status_id' => $status->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $lead->id);

    expect($row['lead_status'])->toBe(['id' => $status->id, 'name' => 'Qualified', 'color' => 'green']);
});

it('rows: a filter on the lead_status column returns only that status\' leads (AC-013)', function () {
    $actor = leadUserWith(['viewAny']);
    $status = LeadStatus::factory()->create(['name' => 'Matching Status']);
    $otherStatus = LeadStatus::factory()->create(['name' => 'Other Status']);
    $matching = Lead::factory()->create(['lead_status_id' => $status->id]);
    Lead::factory()->create(['lead_status_id' => $otherStatus->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/leads/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['lead_status' => ['filterType' => 'set', 'values' => ['Matching Status']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matching->id]);
});

it('rows: sorting by lead_status orders leads by the related status name (AC-013)', function () {
    $actor = leadUserWith(['viewAny']);
    $statusAlpha = LeadStatus::factory()->create(['name' => 'Alpha Status']);
    $statusZulu = LeadStatus::factory()->create(['name' => 'Zulu Status']);
    $leadZulu = Lead::factory()->create(['lead_status_id' => $statusZulu->id]);
    $leadAlpha = Lead::factory()->create(['lead_status_id' => $statusAlpha->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/leads/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'lead_status', 'sort' => 'asc']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$leadAlpha->id, $leadZulu->id]);
});

// ---------------------------------------------------------------------------
// AC-044 — export: created with leads.export, 403 without it
// ---------------------------------------------------------------------------

if (! function_exists('leadExportPayload')) {
    /**
     * @return array<string, mixed>
     */
    function leadExportPayload(): array
    {
        return [
            'format' => 'csv',
            'columns' => [
                ['colId' => 'referent', 'header' => 'Referent'],
                ['colId' => 'campaign', 'header' => 'Campaign'],
            ],
        ];
    }
}

it('201 creates the ExportRun and dispatches GenerateExportJob with leads.export (AC-044)', function () {
    Queue::fake();
    $actor = leadUserWith(['export']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/leads', leadExportPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.export_run.status', 'processing')
        ->assertJsonPath('data.export_run.resource', 'leads')
        ->assertJsonPath('data.export_run.format', 'csv');

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));
    expect($run->user_id)->toBe($actor->id);

    Queue::assertPushed(GenerateExportJob::class);
});

it('403 without leads.export, no ExportRun created (AC-044)', function () {
    Queue::fake();
    $actor = leadUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/leads', leadExportPayload())->assertForbidden();

    expect(ExportRun::count())->toBe(0);
    Queue::assertNotPushed(GenerateExportJob::class);
});
