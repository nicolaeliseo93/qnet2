<?php

namespace Database\Seeders;

use App\Enums\LocaleEnum;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Illuminate\Database\Seeder;

/**
 * Seed the single privileged demo account. This is the only user created by the
 * default database seed; every other record belongs to DemoDataSeeder.
 * Idempotent: the account is upserted by email.
 */
class DemoUserSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    public function run(): void
    {
        $user = User::firstOrNew(['email' => self::DEMO_EMAIL]);
        $user->name = 'Demo User';
        $user->locale = LocaleEnum::It->value;
        $user->email_verified_at ??= now();

        if (! $user->exists) {
            $user->password = config('seeding.password');
        }

        $user->save();
        $user->syncRoles([RoleAssignmentGuard::PRIVILEGED_ROLE]);
    }
}
