<?php

use App\Models\CompanySite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanySiteAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanySiteAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("company-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("company-sites.{$ability}");
        }

        return $user;
    }
}

it('set-default: promotes this site and demotes every other one (invariant: at most one default)', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $current = CompanySite::factory()->default()->create();
    $other = CompanySite::factory()->default()->create(); // seed data could (wrongly) have two
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/company-sites/{$target->id}/set-default")
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect($target->fresh()->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse()
        ->and($other->fresh()->is_default)->toBeFalse()
        ->and(CompanySite::query()->where('is_default', true)->count())->toBe(1);
});

it('set-default: 403 without company-sites.update', function () {
    $actor = userWithCompanySiteAbilities([]);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/company-sites/{$target->id}/set-default")->assertForbidden();
});

it('set-default: 404 for a non-existent site', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites/999999/set-default')->assertNotFound();
});
