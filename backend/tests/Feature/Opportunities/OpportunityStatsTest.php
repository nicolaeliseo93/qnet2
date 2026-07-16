<?php

use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-043 — GET /api/stats/opportunities
// ---------------------------------------------------------------------------

it('403 without opportunities.viewAny (AC-043)', function () {
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/stats/opportunities')->assertForbidden();
});

it('200 with the contract widgets: total, estimated_value, average_probability, by_registry, trend (AC-043)', function () {
    Permission::findOrCreate('opportunities.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.viewAny');

    $registry = Registry::factory()->create(['name' => 'Top Registry']);
    Opportunity::factory()->create(['registry_id' => $registry->id, 'estimated_value' => 1000, 'success_probability' => 40]);
    Opportunity::factory()->create(['registry_id' => $registry->id, 'estimated_value' => 2000, 'success_probability' => 60]);
    Opportunity::factory()->create(['estimated_value' => null, 'success_probability' => null]);

    Sanctum::actingAs($actor);

    $widgets = collect($this->getJson('/api/stats/opportunities')->assertOk()->json('data.widgets'))->keyBy('key');

    expect($widgets['total']['value'])->toBe(3)
        ->and((float) $widgets['estimated_value']['value'])->toBe(3000.0)
        ->and($widgets['estimated_value']['format'])->toBe('currency')
        ->and((float) $widgets['average_probability']['value'])->toBe(50.0)
        ->and($widgets['by_registry']['type'])->toBe('distribution')
        ->and(collect($widgets['by_registry']['items'])->firstWhere('label', 'Top Registry')['value'])->toBe(2)
        ->and($widgets['trend']['type'])->toBe('trend')
        ->and($widgets['trend']['points'])->toHaveCount(12);
});

it('average_probability is null (not 0) when no opportunity has one', function () {
    Permission::findOrCreate('opportunities.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.viewAny');

    Opportunity::factory()->create(['success_probability' => null]);
    Sanctum::actingAs($actor);

    $widgets = collect($this->getJson('/api/stats/opportunities')->assertOk()->json('data.widgets'))->keyBy('key');

    expect($widgets['average_probability']['value'])->toBeNull();
});
