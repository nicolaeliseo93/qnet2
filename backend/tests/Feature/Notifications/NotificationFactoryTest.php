<?php

use App\Models\Notification;
use App\Models\User;
use App\Notifications\GenericNotification;
use Database\Factories\NotificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;

uses(RefreshDatabase::class);

it('is resolvable via the idiomatic Notification::factory() entry point', function () {
    $notification = Notification::factory()->create();

    expect($notification)->toBeInstanceOf(Notification::class);
    $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
});

it('creates an unread notification with the agnostic payload by default', function () {
    $notification = NotificationFactory::new()->create();

    expect($notification)->toBeInstanceOf(DatabaseNotification::class)
        ->and($notification->read_at)->toBeNull()
        ->and($notification->type)->toBe(GenericNotification::class)
        ->and($notification->data)->toHaveKeys(['title', 'message', 'level', 'action_url']);

    $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
});

it('attaches the notification to the given user via forUser', function () {
    $user = User::factory()->create();

    NotificationFactory::new()->forUser($user)->count(3)->create();

    expect($user->notifications()->count())->toBe(3)
        ->and($user->unreadNotifications()->count())->toBe(3);
});

it('marks the notification as read with the read state', function () {
    $user = User::factory()->create();

    NotificationFactory::new()->forUser($user)->read()->create();

    expect($user->notifications()->count())->toBe(1)
        ->and($user->unreadNotifications()->count())->toBe(0);
});

it('overrides the payload level via the level state', function () {
    $notification = NotificationFactory::new()->level('error')->create();

    expect($notification->data['level'])->toBe('error');
});
