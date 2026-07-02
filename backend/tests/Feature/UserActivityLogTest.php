<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs an activity when a user is created', function () {
    $user = User::factory()->create();

    $activity = Activity::query()->where('log_name', 'users')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('created')
        ->and($activity->subject_id)->toBe($user->id)
        // Morph map enforced (AppServiceProvider): the column stores the alias.
        ->and($activity->subject_type)->toBe($user->getMorphClass());
});

it('logs only dirty attributes on update and never the password', function () {
    $user = User::factory()->create();

    $user->update([
        'name' => 'Updated Name',
        'password' => bcrypt('new-secret'),
    ]);

    $activity = Activity::query()->where('description', 'updated')->latest('id')->first();

    $changed = $activity->changes()['attributes'];

    expect($changed)->toHaveKey('name', 'Updated Name')
        ->and($changed)->not->toHaveKey('password');
});

it('links the authenticated user as causer of an activity', function () {
    $causer = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($causer);

    $target->update(['name' => 'Renamed']);

    $activity = Activity::query()->where('description', 'updated')->latest('id')->first();

    expect($activity->causer_id)->toBe($causer->id)
        ->and($activity->causer_type)->toBe($causer->getMorphClass())
        ->and($causer->fresh()->actions()->where('description', 'updated')->exists())->toBeTrue()
        ->and($target->fresh()->activities()->where('description', 'updated')->exists())->toBeTrue();
});
