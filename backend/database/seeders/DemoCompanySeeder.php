<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the companies module (Società aziendali).
 *
 * Each company owns exactly ONE primary address (the module's single-address
 * invariant, spec 0010), tied to a real seeded City so the derived geo columns
 * (comune / provincia / regione / country) are populated. Deterministic faker
 * seed + firstOrNew on the denomination keep the seed idempotent across runs.
 */
class DemoCompanySeeder extends Seeder
{
    private const int COMPANIES = 30;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260703);

        $cities = City::query()
            ->with(['country', 'state', 'province'])
            ->inRandomOrder()
            ->limit(200)
            ->get();

        for ($index = 1; $index <= self::COMPANIES; $index++) {
            $this->seedCompany($faker, $cities, $index);
        }
    }

    /**
     * @param  Collection<int, City>  $cities
     */
    private function seedCompany(Generator $faker, Collection $cities, int $index): void
    {
        $company = Company::firstOrNew(['denomination' => $faker->unique()->company()]);
        $company->vat_number = $faker->numerify('IT###########');
        $company->save();

        $this->seedPrimaryAddress($faker, $company, $cities, $index);
    }

    /**
     * Reconcile to a single primary address (idempotent): drop the owner's
     * addresses, then create one via the real seeded city's full geo ancestry.
     *
     * @param  Collection<int, City>  $cities
     */
    private function seedPrimaryAddress(Generator $faker, Company $company, Collection $cities, int $index): void
    {
        $company->addresses()->delete();

        $city = $cities->isNotEmpty() ? $cities[$index % $cities->count()] : null;

        if ($city === null) {
            Address::factory()->primary()->for($company, 'addressable')->create();

            return;
        }

        Address::factory()->forCity($city)->primary()->for($company, 'addressable')->create([
            'postal_code' => $faker->numerify('#####'),
        ]);
    }
}
