<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Factory for the `notifications` table, via the App\Models\Notification shim
 * (the framework's DatabaseNotification does not use HasFactory). Use either:
 *
 *     Notification::factory()->forUser($user)->create();             // idiomatic
 *     NotificationFactory::new()->forUser($user)->create();          // direct
 *     Notification::factory()->read()->count(5)->create();           // read
 *     Notification::factory()->forUser($user)->state([
 *         'data' => ['title' => 'Hi', 'message' => 'There'],
 *     ])->create();
 *
 * By default it creates an unread notification for a fresh User, with a payload
 * matching the agnostic `{ title, message, level, action_url }` convention.
 *
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The notifications table uses a uuid string primary key (no
            // auto-increment), so it must be supplied explicitly.
            'id' => (string) Str::uuid(),
            'type' => GenericNotification::class,
            // Defaults to a new User; override with forUser()/forNotifiable().
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => User::factory(),
            'data' => [
                'title' => fake()->sentence(3),
                'message' => fake()->sentence(8),
                'level' => fake()->randomElement(['info', 'success', 'warning', 'error']),
                'action_url' => null,
            ],
            'read_at' => null,
        ];
    }

    /**
     * Attach the notification to an existing user (the common case in tests).
     */
    public function forUser(User $user): static
    {
        return $this->forNotifiable($user);
    }

    /**
     * Attach the notification to any notifiable model.
     */
    public function forNotifiable(Model $notifiable): static
    {
        return $this->state(fn (array $attributes): array => [
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
        ]);
    }

    /**
     * Mark the notification as already read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now(),
        ]);
    }

    /**
     * Mark the notification as unread (the default).
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => null,
        ]);
    }

    /**
     * Override the payload level, keeping the rest of the data intact.
     */
    public function level(string $level): static
    {
        return $this->state(fn (array $attributes): array => [
            'data' => [...$attributes['data'], 'level' => $level],
        ]);
    }
}
