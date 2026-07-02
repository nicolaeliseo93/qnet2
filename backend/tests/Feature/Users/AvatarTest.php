<?php

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithUserUpdateAbility')) {
    function userWithUserUpdateAbility(): User
    {
        Permission::findOrCreate('users.update');
        $user = User::factory()->create();
        $user->givePermissionTo('users.update');

        return $user;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

// ---------------------------------------------------------------------------
// self-service — POST/DELETE /api/auth/me/avatar (settings page)
// ---------------------------------------------------------------------------

it('me/avatar: uploads the current user avatar and exposes its url', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/auth/me/avatar', [
        'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
    ])->assertOk()->assertJsonPath('success', true);

    $url = $response->json('data.avatar_url');
    expect($url)->toStartWith('data:image/')->and($url)->toContain(';base64,');

    expect($user->fresh()->avatar)->not->toBeNull();
    Storage::disk('local')->assertExists($user->fresh()->avatar->path);
});

it('me/avatar: replacing keeps a single avatar (old file removed)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/auth/me/avatar', ['avatar' => UploadedFile::fake()->image('one.png')])->assertOk();
    $first = $user->fresh()->avatar;

    $this->postJson('/api/auth/me/avatar', ['avatar' => UploadedFile::fake()->image('two.png')])->assertOk();

    expect(Attachment::where('attachable_id', $user->id)->where('collection', 'avatar')->count())->toBe(1);
    Storage::disk('local')->assertMissing($first->path);
});

it('me/avatar: rejects a non-image file', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/auth/me/avatar', [
        'avatar' => UploadedFile::fake()->create('document.pdf', 16, 'application/pdf'),
    ])->assertUnprocessable()->assertJsonValidationErrors('avatar');
});

it('me/avatar: requires authentication', function () {
    $this->postJson('/api/auth/me/avatar', ['avatar' => UploadedFile::fake()->image('x.png')])
        ->assertUnauthorized();
});

it('me/avatar: removes the current user avatar', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $this->postJson('/api/auth/me/avatar', ['avatar' => UploadedFile::fake()->image('me.png')])->assertOk();
    $path = $user->fresh()->avatar->path;

    $this->deleteJson('/api/auth/me/avatar')
        ->assertOk()
        ->assertJsonPath('data.avatar_url', null);

    expect($user->fresh()->avatar)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

// ---------------------------------------------------------------------------
// admin — POST/DELETE /api/users/{user}/avatar (user form)
// ---------------------------------------------------------------------------

it('users/{user}/avatar: admin with users.update can set another user avatar', function () {
    $admin = userWithUserUpdateAbility();
    $target = User::factory()->create();
    Sanctum::actingAs($admin);

    $this->postJson("/api/users/{$target->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('target.png'),
    ])->assertOk()->assertJsonPath('data.id', $target->id);

    expect($target->fresh()->avatar)->not->toBeNull();
});

it('users/{user}/avatar: 403 without users.update', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/users/{$target->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('target.png'),
    ])->assertForbidden();
});

it('users/{user}/avatar: admin can remove another user avatar', function () {
    $admin = userWithUserUpdateAbility();
    $target = User::factory()->create();
    Sanctum::actingAs($admin);
    $this->postJson("/api/users/{$target->id}/avatar", ['avatar' => UploadedFile::fake()->image('t.png')])->assertOk();

    $this->deleteJson("/api/users/{$target->id}/avatar")
        ->assertOk()
        ->assertJsonPath('data.avatar_url', null);

    expect($target->fresh()->avatar)->toBeNull();
});
