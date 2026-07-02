<?php

namespace Database\Seeders;

use App\Enums\PersonalDataTypeEnum;
use App\Models\PersonalData;
use App\Models\User;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;

/**
 * Seed a realistic personal-data card for every deterministic development user.
 * Idempotent: the card is upserted by owner and the nested contacts/addresses
 * are handled by dedicated seeders.
 */
class PersonalDataSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260619);

        $this->seededUsersQuery()
            ->orderBy('email')
            ->get()
            ->values()
            ->each(fn (User $user, int $index) => $this->seedCard($faker, $user, $index));
    }

    /**
     * Upsert the user's identity card. The profile type stays varied but stable
     * across reruns because the faker sequence is deterministic.
     */
    private function seedCard(Generator $faker, User $user, int $index): void
    {
        $type = $index % 4 === 0 ? PersonalDataTypeEnum::Company : PersonalDataTypeEnum::Individual;

        $attributes = $type === PersonalDataTypeEnum::Company
            ? PersonalData::factory()->company()->make()->getAttributes()
            : PersonalData::factory()->individual()->make()->getAttributes();

        if ($user->email === self::DEMO_EMAIL) {
            $attributes = array_replace($attributes, [
                'type' => PersonalDataTypeEnum::Individual->value,
                'first_name' => 'Demo',
                'last_name' => 'User',
                'company_name' => null,
                'vat_number' => null,
            ]);
        }

        if ($type === PersonalDataTypeEnum::Company) {
            $attributes = array_replace($attributes, [
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'company_name' => sprintf('%s %s', $faker->companySuffix(), $faker->company()),
            ]);
        }

        /** @var PersonalData $card */
        $card = $user->personalData()->updateOrCreate([], $attributes);

        $user->forceFill(['name' => $card->full_name])->save();
    }
}
