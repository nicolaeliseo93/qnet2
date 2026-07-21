<?php

use App\Models\City;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0048 (A): GET /api/users/for-select gains an optional
 * `operational_site_id` filter (employment.operational_site_id) plus a Sede
 * `meta` on every item. Distinct file from UserForSelectTest.php (existing
 * for-select coverage, untouched).
 */
if (! function_exists('siteFilterActor')) {
    function siteFilterActor(): User
    {
        Permission::findOrCreate('users.viewAny');
        $actor = User::factory()->create();
        $actor->givePermissionTo('users.viewAny');

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — no operational_site_id: unchanged (every user, meta only when the
// operator actually has a Sede).
// ---------------------------------------------------------------------------

it('AC-001: without operational_site_id, every user is returned and meta is absent without a Sede', function () {
    $actor = siteFilterActor();
    $noEmployment = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $noEmployment->id);

    expect($item)->not->toBeNull()
        ->and(array_key_exists('meta', $item))->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-002 — operational_site_id filters to users employed at that Sede only.
// ---------------------------------------------------------------------------

it('AC-002: operational_site_id restricts the list to users employed at that Sede', function () {
    $actor = siteFilterActor();
    $siteA = OperationalSite::factory()->withAddress()->create();
    $siteB = OperationalSite::factory()->withAddress()->create();
    $atSiteA = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $atSiteA->id, 'operational_site_id' => $siteA->id]);
    $atSiteB = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $atSiteB->id, 'operational_site_id' => $siteB->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?operational_site_id={$siteA->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($atSiteA->id)
        ->and($ids)->not->toContain($atSiteB->id)
        ->and($ids)->not->toContain($actor->id);
});

it('AC-002: rejects an operational_site_id that does not exist (422)', function () {
    $actor = siteFilterActor();
    Sanctum::actingAs($actor);

    $this->getJson('/api/users/for-select?operational_site_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('operational_site_id');
});

// ---------------------------------------------------------------------------
// AC-003 — meta.operational_site_id + composed label; null without a Sede.
// ---------------------------------------------------------------------------

it('AC-003: an operator with a Sede exposes meta {operational_site_id, operational_site_label}', function () {
    $actor = siteFilterActor();
    $city = City::factory()->create(['name' => 'Springfield']);
    $site = OperationalSite::factory()->withAddress($city)->create();
    $operator = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $operator->id, 'operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $operator->id);
    $address = $site->addresses()->first();

    expect($item['meta'])->toBe([
        'operational_site_id' => $site->id,
        'operational_site_label' => "{$address->line1} - Springfield",
    ]);
});

it('AC-003: an operator without a Sede omits meta entirely', function () {
    $actor = siteFilterActor();
    $operator = User::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $operator->id, 'operational_site_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $operator->id);

    expect(array_key_exists('meta', $item))->toBeFalse();
});
