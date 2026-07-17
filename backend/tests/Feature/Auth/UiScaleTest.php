<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Per-user UI scale (0..100 slider). Persisted as a single `ui_scale` tinyint
 * column on `users`, exposed and saved through the existing GET/PATCH
 * /api/auth/me — no new endpoint. Default is 40 (=100%) when unset.
 */
it('GET /auth/me defaults ui_scale to 40 when unset', function () {
    $user = User::factory()->create(['ui_scale' => null]);
    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.ui_scale', 40);
});

it('PATCH /auth/me persists ui_scale and a following GET reflects it', function () {
    $user = User::factory()->create(['ui_scale' => null]);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['ui_scale' => 75])
        ->assertOk()
        ->assertJsonPath('data.ui_scale', 75);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.ui_scale', 75);

    expect($user->fresh()->ui_scale)->toBe(75);
});

it('accepts the 0 and 100 bounds of the slider', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['ui_scale' => 0])
        ->assertOk()
        ->assertJsonPath('data.ui_scale', 0);

    $this->patchJson('/api/auth/me', ['ui_scale' => 100])
        ->assertOk()
        ->assertJsonPath('data.ui_scale', 100);
});

it('rejects a ui_scale above 100', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['ui_scale' => 101])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ui_scale');
});

it('rejects a negative ui_scale', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['ui_scale' => -1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ui_scale');
});

it('rejects a non-integer ui_scale', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['ui_scale' => 'big'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ui_scale');
});

it('updating ui_scale alongside locale leaves both correct, no regression', function () {
    $user = User::factory()->create(['locale' => 'en', 'ui_scale' => null]);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['locale' => 'it', 'ui_scale' => 60])
        ->assertOk()
        ->assertJsonPath('data.locale', 'it')
        ->assertJsonPath('data.ui_scale', 60);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'it', 'ui_scale' => 60]);
});

it('omitting ui_scale from the payload leaves the stored value untouched', function () {
    $user = User::factory()->create(['ui_scale' => 80]);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['locale' => 'it'])
        ->assertOk()
        ->assertJsonPath('data.ui_scale', 80);

    expect($user->fresh()->ui_scale)->toBe(80);
});

it('only ever touches the authenticated user, never another one', function () {
    $other = User::factory()->create(['ui_scale' => null]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['ui_scale' => 90])->assertOk();

    expect($other->fresh()->ui_scale)->toBeNull();
});
