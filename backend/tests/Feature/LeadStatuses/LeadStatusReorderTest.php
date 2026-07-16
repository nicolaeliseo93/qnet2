<?php

use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/lead-statuses/reorder (spec 0039, D-5, AC-005)
|--------------------------------------------------------------------------
*/

if (! function_exists('leadStatusReorderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadStatusReorderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("lead-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("lead-statuses.{$ability}");
        }

        return $user;
    }
}

it('reorder: a valid permutation resequences the customs, Nuovo stays 0, Chiuso stays last (AC-005)', function () {
    $actor = leadStatusReorderUserWith(['update']);
    $first = LeadStatus::factory()->create(['name' => 'Alpha']);
    $second = LeadStatus::factory()->create(['name' => 'Beta']);
    $third = LeadStatus::factory()->create(['name' => 'Gamma']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/lead-statuses/reorder', [
        'ordered_ids' => [$third->id, $first->id, $second->id],
    ])->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');
    expect($rows[$third->id]['sort_order'])->toBe(10)
        ->and($rows[$first->id]['sort_order'])->toBe(20)
        ->and($rows[$second->id]['sort_order'])->toBe(30);

    $newRow = $rows->firstWhere('system_key', 'new');
    $closedRow = $rows->firstWhere('system_key', 'closed');
    expect($newRow['sort_order'])->toBe(0)
        ->and($closedRow['sort_order'])->toBe(40);
});

it('reorder: 422 when ordered_ids includes a system status id (AC-005)', function () {
    $actor = leadStatusReorderUserWith(['update']);
    $newStatus = LeadStatus::where('system_key', 'new')->firstOrFail();
    $custom = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses/reorder', ['ordered_ids' => [$newStatus->id, $custom->id]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids is missing a custom id (AC-005)', function () {
    $actor = leadStatusReorderUserWith(['update']);
    LeadStatus::factory()->create();
    $second = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses/reorder', ['ordered_ids' => [$second->id]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids contains a duplicate (AC-005)', function () {
    $actor = leadStatusReorderUserWith(['update']);
    $custom = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses/reorder', ['ordered_ids' => [$custom->id, $custom->id]])
        ->assertStatus(422)->assertJsonValidationErrors('ordered_ids.0');
});

it('reorder: 422 when ordered_ids includes a non-existent id (AC-005)', function () {
    $actor = leadStatusReorderUserWith(['update']);
    $custom = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses/reorder', ['ordered_ids' => [$custom->id, 999999]])
        ->assertStatus(422);
});

it('reorder: 403 without lead-statuses.update, order unchanged (AC-005)', function () {
    $actor = leadStatusReorderUserWith([]);
    $custom = LeadStatus::factory()->create(['sort_order' => 20]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses/reorder', ['ordered_ids' => [$custom->id]])->assertForbidden();

    $this->assertDatabaseHas('lead_statuses', ['id' => $custom->id, 'sort_order' => 20]);
});
