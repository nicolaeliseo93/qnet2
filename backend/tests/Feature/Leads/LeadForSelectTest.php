<?php

use App\Models\Lead;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadForSelectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadForSelectUserWith(array $abilities): User
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
// AC-085 — auth + shape (envelope ADR 0011, label = referent name)
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/leads/for-select')->assertUnauthorized();
});

it('forbids actors without leads.viewAny (403)', function () {
    $actor = leadForSelectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/leads/for-select')->assertForbidden();
});

it('200: envelope {items, export_link, pagination} with {id, label, subtitle} items, filtered by search (AC-085)', function () {
    $actor = leadForSelectUserWith(['viewAny']);
    $matchingReferent = Referent::factory()->create(['name' => 'Ada Contact']);
    $match = Lead::factory()->create(['referent_id' => $matchingReferent->id]);
    Lead::factory()->create(['referent_id' => Referent::factory()->create(['name' => 'Zed Other'])->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/leads/for-select?search=Ada')->assertOk();

    expect($response->json('export_link'))->toBeNull()
        ->and($response->json('pagination'))->toHaveKeys(['total', 'offset', 'limit', 'total_pages']);

    $items = $response->json('items');
    expect($items)->toHaveCount(1);
    expect($items[0])->toMatchArray([
        'id' => $match->id,
        'label' => 'Ada Contact',
        'subtitle' => $match->campaign->code,
    ]);
});

// ---------------------------------------------------------------------------
// AC-085 — ids[] hydration bypasses search
// ---------------------------------------------------------------------------

it('ids[] hydrates a lead present even though it does not match the search (AC-085)', function () {
    $actor = leadForSelectUserWith(['viewAny']);
    $hydrated = Lead::factory()->create(['referent_id' => Referent::factory()->create(['name' => 'Legacy Contact'])->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/for-select?search=NoMatch&ids[]={$hydrated->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toContain($hydrated->id);
});
