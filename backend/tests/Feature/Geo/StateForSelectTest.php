<?php

use App\Models\Country;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// auth — no Policy, reference data gated only by auth:sanctum (spec 0023)
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/states/for-select')->assertUnauthorized();
});

it('allows any authenticated actor (200) and returns the paginated envelope', function () {
    Sanctum::actingAs(User::factory()->create());
    State::factory()->count(3)->create();

    $this->getJson('/api/states/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label', 'subtitle']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// item shape + search
// ---------------------------------------------------------------------------

it('maps a state to { id, label: name, subtitle: country.name }', function () {
    Sanctum::actingAs(User::factory()->create());
    $country = Country::factory()->create(['name' => 'Italy']);
    $target = State::factory()->for($country, 'country')->create(['name' => 'Veneto']);

    $response = $this->getJson('/api/states/for-select?search=Veneto')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Veneto', 'subtitle' => 'Italia'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'subtitle']);
});

it('searches by name', function () {
    Sanctum::actingAs(User::factory()->create());
    $match = State::factory()->create(['name' => 'Alphonse Target']);
    State::factory()->create(['name' => 'Someone Else']);

    $response = $this->getJson('/api/states/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    Sanctum::actingAs(User::factory()->create());
    $searchMatch = State::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = State::factory()->create(['name' => 'Quentin Selected']);

    $response = $this->getJson("/api/states/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/states/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

// ---------------------------------------------------------------------------
// N+1 — country must be eager-loaded, not queried per row
// ---------------------------------------------------------------------------

it('does not issue one country query per state (eager-loaded)', function () {
    Sanctum::actingAs(User::factory()->create());
    State::factory()->count(10)->create();

    DB::enableQueryLog();
    $this->getJson('/api/states/for-select')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $countryQueries = $queries->filter(fn (string $sql) => str_contains($sql, 'from `countries`') || str_contains($sql, 'from "countries"'));

    expect($countryQueries)->toHaveCount(1);
});
