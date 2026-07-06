<?php

namespace Database\Seeders;

use App\Enums\PersonalDataTypeEnum;
use App\Models\Address;
use App\Models\City;
use App\Models\PersonalData;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;

class DemoUserAddressSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260618);

        $cities = City::query()->with(['country', 'state', 'province'])->inRandomOrder()->limit(400)->get();

        $this->seededUsersQuery()
            ->with('personalData')
            ->whereHas('personalData')
            ->orderBy('email')
            ->get()
            ->values()
            ->each(fn ($user, $index) => $this->seedAddresses($faker, $user->personalData, $cities, $index));
    }

    private function seedAddresses(Generator $faker, PersonalData $card, $cities, int $index): void
    {
        $card->addresses()->delete();

        $primaryCity = $cities[$index % max($cities->count(), 1)] ?? City::query()->with(['country', 'state', 'province'])->first();

        if ($primaryCity === null) {
            Address::factory()->primary()->for($card, 'addressable')->create();

            return;
        }

        Address::factory()->forCity($primaryCity)->primary()->for($card, 'addressable')->create([
            'postal_code' => $this->postalCode($faker, $primaryCity->country?->iso2),
        ]);

        if ($card->type === PersonalDataTypeEnum::Company) {
            $secondaryCity = $cities[($index + 17) % $cities->count()] ?? $primaryCity;

            Address::factory()->forCity($secondaryCity)->for($card, 'addressable')->create([
                'postal_code' => $this->postalCode($faker, $secondaryCity->country?->iso2),
            ]);

            if ($faker->boolean(55)) {
                $warehouseCity = $cities[($index + 43) % $cities->count()] ?? $secondaryCity;

                Address::factory()->forCity($warehouseCity)->for($card, 'addressable')->create([
                    'postal_code' => $this->postalCode($faker, $warehouseCity->country?->iso2),
                ]);
            }

            return;
        }

        if ($faker->boolean(30)) {
            $secondaryCity = $cities[($index + 9) % $cities->count()] ?? $primaryCity;

            Address::factory()->forCity($secondaryCity)->for($card, 'addressable')->create([
                'postal_code' => $this->postalCode($faker, $secondaryCity->country?->iso2),
            ]);
        }
    }

    private function postalCode(Generator $faker, ?string $countryCode): string
    {
        return match (strtoupper((string) $countryCode)) {
            'US' => $faker->numerify('#####'),
            'GB' => strtoupper($faker->bothify('?## #??')),
            default => $faker->numerify('#####'),
        };
    }
}
