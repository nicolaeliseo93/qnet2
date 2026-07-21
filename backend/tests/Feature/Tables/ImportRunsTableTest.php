<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

if (! function_exists('importRunsTableUserWith')) {
    /**
     * @param  array<int, string>  $abilities  a non-empty list grants the import
     *                                         module's single `leads.import` ability
     *                                         (the former `import-runs.*` set was
     *                                         removed 2026-07-17); [] grants nothing.
     */
    function importRunsTableUserWith(array $abilities): User
    {
        $user = User::factory()->create();
        grantImportRunsPermissions($user, $abilities);

        return $user;
    }
}

// ---------------------------------------------------------------------------
// Authorization — dominio `import-runs` (spec 0034: rinominato da `lead-imports`).
// Il set dedicato import-runs.* e' stato rimosso (2026-07-17): la tabella e'
// gated dal `leads.import` del modulo lead, via ImportRunPolicy::viewAny.
// ---------------------------------------------------------------------------

it('GET /api/tables/import-runs/columns: 403 without leads.import, 200 with it', function () {
    Sanctum::actingAs(importRunsTableUserWith([]));
    $this->getJson('/api/tables/import-runs/columns')->assertForbidden();

    Sanctum::actingAs(importRunsTableUserWith(['viewAny']));

    $data = $this->getJson('/api/tables/import-runs/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('import-runs')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['original_filename']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'created_at', 'original_filename', 'total_rows', 'imported_rows', 'invalid_rows', 'status']);
});

it('POST /api/tables/import-runs/rows: 403 without leads.import', function () {
    Sanctum::actingAs(importRunsTableUserWith([]));
    $this->postJson('/api/tables/import-runs/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

// ---------------------------------------------------------------------------
// status badge column — backend-driven color/label metadata
// ---------------------------------------------------------------------------

it('exposes the status column as a badge driven by ImportStatus (options + badges + enumKey)', function () {
    Sanctum::actingAs(importRunsTableUserWith(['viewAny']));

    $columns = collect($this->getJson('/api/tables/import-runs/columns')->json('data.columns'))->keyBy('id');
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
    $actor = importRunsTableUserWith(['viewAny', 'view', 'delete']);

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

    $items = $this->postJson('/api/tables/import-runs/rows', ['startRow' => 0, 'endRow' => 25])
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
    $actor = importRunsTableUserWith(['viewAny', 'delete']);
    $own = ImportRun::factory()->create(['resource' => 'leads', 'user_id' => $actor->id]);
    $foreign = ImportRun::factory()->create(['resource' => 'leads', 'user_id' => User::factory()->create()->id]);

    Sanctum::actingAs($actor);

    $result = $this->postJson('/api/tables/import-runs/bulk-delete', ['ids' => [$own->id, $foreign->id]])
        ->assertOk()
        ->json('data');

    expect($result['deleted'])->toBe(1);
    $this->assertDatabaseMissing('import_runs', ['id' => $own->id]);
    // Foreign run is outside baseQuery's scope → reported not_found, never deleted.
    $this->assertDatabaseHas('import_runs', ['id' => $foreign->id]);
    expect(collect($result['failed'])->firstWhere('id', $foreign->id)['reason'])->toBe('not_found');
});

it('bulk-delete 403 without leads.import, leaving the run untouched', function () {
    $actor = importRunsTableUserWith([]);
    $own = ImportRun::factory()->create(['resource' => 'leads', 'user_id' => $actor->id]);

    Sanctum::actingAs($actor);

    // No leads.import -> the table's viewAny gate (ImportRunPolicy) denies the
    // whole endpoint before any per-row check. With a single gate there is no
    // longer a "has table access but not delete" state to produce a per-row
    // 'forbidden' reason.
    $this->postJson('/api/tables/import-runs/bulk-delete', ['ids' => [$own->id]])->assertForbidden();
    $this->assertDatabaseHas('import_runs', ['id' => $own->id]);
});
