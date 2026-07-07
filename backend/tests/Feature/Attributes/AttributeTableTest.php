<?php

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('attributeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attributeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("attributes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("attributes.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-006 — columns config
// ---------------------------------------------------------------------------

it('returns the 4 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = attributeUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/attributes/columns')->assertForbidden();

    $actor = attributeUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/attributes/columns')
        ->assertOk()
        ->json('data');

    expect($data['resource'])->toBe('attributes')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']]);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['code', 'name', 'data_type', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['data_type']['type'])->toBe('badge')
        ->and($columns['data_type']['filterType'])->toBe('set')
        ->and($columns['data_type']['enumKey'])->toBe('attribute_type')
        ->and($columns['data_type']['badges'])->toHaveCount(5);
});

// ---------------------------------------------------------------------------
// AC-006 — rows shape
// ---------------------------------------------------------------------------

it('rows expose id/code/name/data_type/options_count/created_at + per-row actions', function () {
    $actor = attributeUserWith(['viewAny', 'view', 'update', 'delete']);
    Attribute::factory()->enum(3)->create(['code' => 'color']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/attributes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('code', 'color');

    expect($row)->not->toBeNull()
        ->and($row['data_type'])->toBe('ENUM')
        ->and($row['options_count'])->toBe(3)
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('resolves distinct data_type values via /values', function () {
    $actor = attributeUserWith(['viewAny']);
    Attribute::factory()->create(['data_type' => AttributeType::String]);
    Attribute::factory()->create(['data_type' => AttributeType::Integer]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/attributes/values', ['columnId' => 'data_type'])->assertOk();

    expect($response->json('data.values'))->toEqualCanonicalizing(['STRING', 'INTEGER']);
});
