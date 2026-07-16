<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-003 — GET /api/meta/import-runs (ImportRunsAuthorization)
// ---------------------------------------------------------------------------

it('403 without import-runs.viewAny', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/import-runs')->assertForbidden();
});

it('200: every field is visible+readonly (the run is never hand-edited)', function () {
    $actor = User::factory()->create();
    grantImportRunsPermissions($actor, ['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/import-runs')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe([
        'original_filename', 'status', 'total_rows', 'imported_rows',
        'invalid_rows', 'warning_rows', 'duplicate_rows', 'modified_rows', 'created_at',
    ]);

    foreach ($response->json('permissions.fields') as $field) {
        expect($field['visible'])->toBeTrue()
            ->and($field['readonly'])->toBeTrue()
            ->and($field['editable'])->toBeFalse();
    }
});

it('permissions.actions maps delete/export to import-runs.{delete,export}', function () {
    $actor = User::factory()->create();
    grantImportRunsPermissions($actor, ['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/import-runs')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true);
});
