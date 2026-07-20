<?php

use App\Models\OpportunityStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/opportunity-statuses/reorder (spec 0043, BR-6)
|--------------------------------------------------------------------------
*/

if (! function_exists('opportunityStatusReorderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityStatusReorderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunity-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunity-statuses.{$ability}");
        }

        return $user;
    }
}

it('reorder: a valid permutation resequences the customs, Nuova stays 0, won then lost stay last (BR-6)', function () {
    $actor = opportunityStatusReorderUserWith(['update']);
    $first = OpportunityStatus::factory()->create(['name' => 'Alpha']);
    $second = OpportunityStatus::factory()->create(['name' => 'Beta']);
    $third = OpportunityStatus::factory()->create(['name' => 'Gamma']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunity-statuses/reorder', [
        'ordered_ids' => [$third->id, $first->id, $second->id],
    ])->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');
    expect($rows[$third->id]['sort_order'])->toBe(10)
        ->and($rows[$first->id]['sort_order'])->toBe(20)
        ->and($rows[$second->id]['sort_order'])->toBe(30);

    $newRow = $rows->firstWhere('system_key', 'new');
    $wonRow = $rows->firstWhere('system_key', 'won');
    $lostRow = $rows->firstWhere('system_key', 'lost');
    expect($newRow['sort_order'])->toBe(0)
        ->and($wonRow['sort_order'])->toBe(40)
        ->and($lostRow['sort_order'])->toBe(50);
});

it('reorder: 422 when ordered_ids includes a system status id (BR-6)', function () {
    $actor = opportunityStatusReorderUserWith(['update']);
    $newStatus = OpportunityStatus::where('system_key', 'new')->firstOrFail();
    $custom = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses/reorder', ['ordered_ids' => [$newStatus->id, $custom->id]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids is missing a custom id (BR-6)', function () {
    $actor = opportunityStatusReorderUserWith(['update']);
    OpportunityStatus::factory()->create();
    $second = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses/reorder', ['ordered_ids' => [$second->id]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids contains a duplicate (BR-6)', function () {
    $actor = opportunityStatusReorderUserWith(['update']);
    $custom = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses/reorder', ['ordered_ids' => [$custom->id, $custom->id]])
        ->assertStatus(422)->assertJsonValidationErrors('ordered_ids.0');
});

it('reorder: 422 when ordered_ids includes a non-existent id (BR-6)', function () {
    $actor = opportunityStatusReorderUserWith(['update']);
    $custom = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses/reorder', ['ordered_ids' => [$custom->id, 999999]])
        ->assertStatus(422);
});

it('reorder: 403 without opportunity-statuses.update, order unchanged (BR-6)', function () {
    $actor = opportunityStatusReorderUserWith([]);
    $custom = OpportunityStatus::factory()->create(['sort_order' => 20]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses/reorder', ['ordered_ids' => [$custom->id]])->assertForbidden();

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $custom->id, 'sort_order' => 20]);
});
