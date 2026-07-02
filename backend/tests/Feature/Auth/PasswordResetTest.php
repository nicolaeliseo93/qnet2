<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('sends a reset link to an existing user', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->postJson('/api/auth/forgot-password', [
        'email' => $user->email,
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('returns the same generic response for an unknown email (no enumeration)', function () {
    Notification::fake();

    $response = $this->postJson('/api/auth/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    Notification::assertNothingSent();
});

it('validates the email on forgot-password', function () {
    $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('requires password confirmation on reset', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'different',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('revokes existing access tokens after a reset', function () {
    $user = User::factory()->create();
    $user->createToken('api');
    $user->createToken('mobile');
    expect($user->tokens()->count())->toBe(2);

    $token = Password::createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(0);
});
