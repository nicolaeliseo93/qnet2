<?php

namespace App\Models;

use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Thin Eloquent model over the framework `notifications` table.
 *
 * It exists ONLY to enable a model factory: the framework model that the
 * `notify()` flow actually writes — Illuminate\Notifications\DatabaseNotification
 * — does not use HasFactory, so `DatabaseNotification::factory()` (or the
 * `Notification` facade) throws. Use this model for seeding/testing instead:
 *
 *     Notification::factory()->forUser($user)->create();        // unread
 *     Notification::factory()->read()->count(5)->create();      // read
 *
 * It extends DatabaseNotification, so rows behave identically (same table,
 * casts, markAsRead) and are returned by `$user->notifications()`. This is NOT a
 * domain model — it adds no behavior and is exempt from the domain-model rules
 * (BaseModel / activity log / Policy), like other framework-backed models.
 *
 * @extends DatabaseNotification
 */
class Notification extends DatabaseNotification
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return NotificationFactory::new();
    }
}
