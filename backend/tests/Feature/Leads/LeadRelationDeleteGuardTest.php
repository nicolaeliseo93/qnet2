<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Referent;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * BR-2/D-4 (spec 0024): none of the 5 entities a Lead references may be
 * deleted while that Lead exists. AC-020..024 exercise the 409 guard on each
 * referenced module's own DELETE endpoint; AC-025 (no false positive on an
 * unreferenced row) is exercised by the pre-existing delete suites of those
 * same 5 modules, run alongside this file (see handoff — LeadCrudTest already
 * proves a fresh factory-created Lead deletes cleanly with 204).
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-020 — Campaign
// ---------------------------------------------------------------------------

it('a campaign referenced by a lead cannot be deleted: 409, not deleted (AC-020)', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("campaigns.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('campaigns.delete');

    $campaign = Campaign::factory()->create();
    Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/campaigns/{$campaign->id}")->assertStatus(409);

    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
});

// ---------------------------------------------------------------------------
// AC-021 — Referent
// ---------------------------------------------------------------------------

it('a referent referenced by a lead cannot be deleted: 409, not deleted (AC-021)', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("referents.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('referents.delete');

    $referent = Referent::factory()->create();
    Lead::factory()->create(['referent_id' => $referent->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referents/{$referent->id}")->assertStatus(409);

    $this->assertDatabaseHas('referents', ['id' => $referent->id]);
});

// ---------------------------------------------------------------------------
// AC-022 — OperationalSite
// ---------------------------------------------------------------------------

it('an operational site referenced by a lead cannot be deleted: 409, not deleted (AC-022)', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("operational-sites.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('operational-sites.delete');

    $site = OperationalSite::factory()->create();
    Lead::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/operational-sites/{$site->id}")->assertStatus(409);

    $this->assertDatabaseHas('operational_sites', ['id' => $site->id]);
});

// ---------------------------------------------------------------------------
// AC-023 — Source
// ---------------------------------------------------------------------------

it('a source referenced by a lead cannot be deleted: 409, not deleted (AC-023)', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("sources.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('sources.delete');

    $source = Source::factory()->create();
    Lead::factory()->create(['source_id' => $source->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/sources/{$source->id}")->assertStatus(409);

    $this->assertDatabaseHas('sources', ['id' => $source->id]);
});

// ---------------------------------------------------------------------------
// AC-024 — User (operator)
// ---------------------------------------------------------------------------

it('a user referenced as a lead operator cannot be deleted: 409, not deleted (AC-024)', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("users.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.delete');

    $operator = User::factory()->create();
    Lead::factory()->create(['operator_id' => $operator->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/users/{$operator->id}")->assertStatus(409);

    $this->assertDatabaseHas('users', ['id' => $operator->id]);
});
