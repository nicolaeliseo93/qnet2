<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed in-app notifications for every user in the personal-data flow (selected
 * via User::withPersonalData()), so the bell/badge and the infinite-scroll panel
 * have realistic data out of the box. Idempotent: each user's notifications are
 * cleared first so re-running does not pile up.
 */
class DemoNotificationSeeder extends Seeder
{
    public function run(): void
    {
        User::withPersonalData()->get()->each($this->seedNotificationsFor(...));
    }

    private function seedNotificationsFor(User $user): void
    {
        $user->notifications()->delete();

        // A few curated, realistic unread notifications (one per level).
        $curated = [
            ['title' => 'Welcome aboard', 'message' => 'Your account is ready. Take a tour of the dashboard.', 'level' => 'info'],
            ['title' => 'Profile updated', 'message' => 'Your profile changes were saved successfully.', 'level' => 'success'],
            ['title' => 'Password expiring', 'message' => 'Your password will expire in 5 days. Consider updating it.', 'level' => 'warning'],
            ['title' => 'Build failed', 'message' => 'Pipeline #128 failed during the deploy step.', 'level' => 'error'],
        ];

        foreach ($curated as $data) {
            Notification::factory()->forUser($user)->state([
                'data' => [...$data, 'action_url' => null],
            ])->create();
        }

        // Plus a batch of already-read random notifications to fill the list and
        // exercise the infinite scroll.
        Notification::factory()->forUser($user)->read()->count(20)->create();
    }
}
