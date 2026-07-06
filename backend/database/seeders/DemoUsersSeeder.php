<?php

namespace Database\Seeders;

use App\Enums\LocaleEnum;
use App\Models\User;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoUsersSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    private const int USERS = 50;

    /**
     * Keep the first five users pinned so every required role is always present.
     *
     * @var array<int, string>
     */
    private const array BASE_ROLES = ['admin', 'manager', 'operator', 'user', 'viewer'];

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260616);

        for ($index = 1; $index <= self::USERS; $index++) {
            $this->seedGeneratedUser($faker, $index);
        }
    }

    private function seedGeneratedUser(Generator $faker, int $index): void
    {
        $firstName = $faker->firstName();
        $lastName = $faker->lastName();
        $email = sprintf(
            '%s.%s.%02d@%s',
            Str::slug($firstName),
            Str::slug($lastName),
            $index,
            self::SEEDED_EMAIL_DOMAIN,
        );

        $user = User::firstOrNew(['email' => $email]);
        $user->name = trim("{$firstName} {$lastName}");
        $user->locale = $this->localeForIndex($index);
        $user->email_verified_at ??= now();

        if (! $user->exists) {
            $user->password = config('seeding.password');
        }

        $user->save();
        $user->syncRoles([$this->roleForIndex($faker, $index)]);
    }

    private function localeForIndex(int $index): string
    {
        return $index % 4 === 0 ? LocaleEnum::En->value : LocaleEnum::It->value;
    }

    private function roleForIndex(Generator $faker, int $index): string
    {
        if ($index <= count(self::BASE_ROLES)) {
            return self::BASE_ROLES[$index - 1];
        }

        return $faker->randomElement([
            'viewer',
            'user',
            'user',
            'operator',
            'operator',
            'manager',
            'admin',
        ]);
    }
}
