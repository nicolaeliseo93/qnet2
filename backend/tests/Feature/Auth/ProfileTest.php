<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('updates the locale successfully', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'locale' => 'en',
    ]);
    Sanctum::actingAs($user);

    $response = $this->patchJson('/api/auth/me', [
        'locale' => 'it',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'old@example.com')
        ->assertJsonPath('data.locale', 'it');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'old@example.com',
        'locale' => 'it',
    ]);
});

it('ignores a submitted email (read-only registration email) and keeps it unchanged', function () {
    $user = User::factory()->create([
        'email' => 'registration@example.com',
        'locale' => 'en',
    ]);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'email' => 'new@example.com',
        'locale' => 'it',
    ])->assertOk()
        ->assertJsonPath('data.email', 'registration@example.com')
        ->assertJsonPath('data.locale', 'it');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'registration@example.com',
        'locale' => 'it',
    ]);
});

it('rejects an unsupported locale', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'locale' => 'fr',
    ])->assertStatus(422)->assertJsonValidationErrors('locale');
});

it('requires authentication to update the profile', function () {
    $this->patchJson('/api/auth/me', [
        'email' => 'someone@example.com',
        'locale' => 'en',
    ])->assertStatus(401);
});

it('changes the password with a correct current_password', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('current-password'),
    ]);
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/me/password', [
        'current_password' => 'current-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk()->assertJsonPath('success', true);

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();

    $this->postJson('/api/auth/login', [
        'email' => 'user@example.com',
        'password' => 'new-password-123',
    ])->assertOk()->assertJsonPath('success', true);
});

it('rejects a wrong current_password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/me/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');
});

it('requires authentication to change the password', function () {
    $this->putJson('/api/auth/me/password', [
        'current_password' => 'current-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertStatus(401);
});

it('revokes other tokens but keeps the current one', function () {
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);

    // An additional, separate session token that must be revoked.
    $user->createToken('other-device');

    // Authenticate through a real bearer token so currentAccessToken() resolves
    // to the persisted token used for this request.
    $current = $user->createToken('current-device');

    expect($user->tokens()->count())->toBe(2);

    $this->withToken($current->plainTextToken)
        ->putJson('/api/auth/me/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

    $remaining = $user->fresh()->tokens()->get();
    expect($remaining)->toHaveCount(1);
    expect($remaining->first()->id)->toBe($current->accessToken->id);
});
