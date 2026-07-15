<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadImportUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadImportUserWith(array $abilities): User
    {
        Permission::findOrCreate('leads.import');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// Authorization — reuses leads.import (no dedicated import-runs permission)
// ---------------------------------------------------------------------------

it('GET /api/tables/lead-imports/columns: 403 without leads.import, 200 with it', function () {
    Sanctum::actingAs(leadImportUserWith([]));
    $this->getJson('/api/tables/lead-imports/columns')->assertForbidden();

    Sanctum::actingAs(leadImportUserWith(['leads.import']));

    $data = $this->getJson('/api/tables/lead-imports/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('lead-imports')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['original_filename']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['created_at', 'original_filename', 'total_rows', 'imported_rows', 'invalid_rows', 'status']);
});

it('POST /api/tables/lead-imports/rows: 403 without leads.import', function () {
    Sanctum::actingAs(leadImportUserWith([]));
    $this->postJson('/api/tables/lead-imports/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

// ---------------------------------------------------------------------------
// status badge column — backend-driven color/label metadata
// ---------------------------------------------------------------------------

it('exposes the status column as a badge driven by ImportStatus (options + badges + enumKey)', function () {
    Sanctum::actingAs(leadImportUserWith(['leads.import']));

    $columns = collect($this->getJson('/api/tables/lead-imports/columns')->json('data.columns'))->keyBy('id');
    $status = $columns['status'];

    expect($status['type'])->toBe('badge')
        ->and($status['enumKey'])->toBe('import_status')
        ->and($status['options'])->toContain('completed', 'failed')
        ->and(collect($status['badges'])->firstWhere('value', 'completed')['color'])->toBe('green')
        ->and(collect($status['badges'])->firstWhere('value', 'failed')['color'])->toBe('red');
});

// ---------------------------------------------------------------------------
// rows — scoped to the actor's OWN runs for the leads resource only
// ---------------------------------------------------------------------------

it('rows expose the mapped fields + per-row actions, scoped to own leads runs', function () {
    $actor = leadImportUserWith(['leads.import']);

    $own = ImportRun::factory()->create([
        'resource' => 'leads',
        'user_id' => $actor->id,
        'status' => ImportStatus::Completed,
        'original_filename' => 'my-leads.csv',
        'total_rows' => 10,
        'invalid_rows' => 2,
        'imported_rows' => 8,
    ]);
    // Another user's leads run — must NOT appear.
    ImportRun::factory()->create(['resource' => 'leads', 'user_id' => User::factory()->create()->id]);
    // The actor's run for a DIFFERENT resource — must NOT appear.
    ImportRun::factory()->create(['resource' => 'companies', 'user_id' => $actor->id]);

    Sanctum::actingAs($actor);

    $items = $this->postJson('/api/tables/lead-imports/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items');

    expect($items)->toHaveCount(1);

    $row = $items[0];
    expect($row['id'])->toBe($own->id)
        ->and($row['original_filename'])->toBe('my-leads.csv')
        ->and($row['total_rows'])->toBe(10)
        ->and($row['imported_rows'])->toBe(8)
        ->and($row['invalid_rows'])->toBe(2)
        ->and($row['status'])->toBe('completed')
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'delete']);
});

// ---------------------------------------------------------------------------
// delete — through the generic bulk-delete engine (ImportRunPolicy)
// ---------------------------------------------------------------------------

it('bulk-delete removes an own leads run, and cannot reach another user run', function () {
    $actor = leadImportUserWith(['leads.import']);
    $own = ImportRun::factory()->create(['resource' => 'leads', 'user_id' => $actor->id]);
    $foreign = ImportRun::factory()->create(['resource' => 'leads', 'user_id' => User::factory()->create()->id]);

    Sanctum::actingAs($actor);

    $result = $this->postJson('/api/tables/lead-imports/bulk-delete', ['ids' => [$own->id, $foreign->id]])
        ->assertOk()
        ->json('data');

    expect($result['deleted'])->toBe(1);
    $this->assertDatabaseMissing('import_runs', ['id' => $own->id]);
    // Foreign run is outside baseQuery's scope → reported not_found, never deleted.
    $this->assertDatabaseHas('import_runs', ['id' => $foreign->id]);
    expect(collect($result['failed'])->firstWhere('id', $foreign->id)['reason'])->toBe('not_found');
});
