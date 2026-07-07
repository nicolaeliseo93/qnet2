<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the company-sites module (spec 0020 — "Società Sedi").
 *
 * Each seeded company gets 1-2 sites (alternating by its own position, so the
 * pattern is stable across re-runs), each with a primary address (tied to a
 * real seeded City, mirroring DemoCompanySeeder) and 0-2 banks. Exactly ONE
 * site across the WHOLE table ends up `is_default` (the flag is globally
 * exclusive, not per-company — see CompanySiteService::setDefault): `$default
 * Assigned` is a monotonic flag, set once and never un-set, so it survives
 * every site regardless of that site's own outcome. Deterministic faker seed
 * + firstOrNew on `email` keep the seed idempotent.
 */
class DemoCompanySiteSeeder extends Seeder
{
    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260707);

        $companies = Company::query()->get();
        $cities = City::query()
            ->with(['country', 'state', 'province'])
            ->inRandomOrder()
            ->limit(200)
            ->get();

        if ($companies->isEmpty() || $cities->isEmpty()) {
            return;
        }

        $sequence = 0;
        $defaultAssigned = false;

        foreach ($companies as $companyPosition => $company) {
            // Alternate 1/2 sites by the company's own position (stable
            // across re-runs, unlike an accumulator carried across companies).
            $siteCount = $companyPosition % 2 === 0 ? 1 : 2;

            for ($site = 0; $site < $siteCount; $site++) {
                $sequence++;
                $this->seedSite($faker, $company, $cities, $sequence, ! $defaultAssigned);
                $defaultAssigned = true;
            }
        }
    }

    /**
     * @param  Collection<int, City>  $cities
     */
    private function seedSite(Generator $faker, Company $company, Collection $cities, int $sequence, bool $isDefault): void
    {
        $companySite = CompanySite::firstOrNew(['email' => $faker->unique()->companyEmail()]);
        $companySite->fill([
            'name' => $faker->company().' - '.$faker->city(),
            'company_id' => $company->id,
            'fiscal_code' => $faker->optional()->numerify('FISC###########'),
            'vat_number' => $faker->numerify('IT###########'),
            'phone' => $faker->phoneNumber(),
            'is_default' => $isDefault,
        ]);
        $companySite->save();

        $this->seedPrimaryAddress($faker, $companySite, $cities, $sequence);
        $this->seedBanks($faker, $companySite, $sequence);
    }

    /**
     * Reconcile to a single primary address (idempotent): drop the owner's
     * addresses, then create one via the real seeded city's full geo ancestry.
     *
     * @param  Collection<int, City>  $cities
     */
    private function seedPrimaryAddress(Generator $faker, CompanySite $companySite, Collection $cities, int $sequence): void
    {
        $companySite->addresses()->delete();

        $city = $cities[$sequence % $cities->count()];

        Address::factory()->forCity($city)->primary()->for($companySite, 'addressable')->create([
            'postal_code' => $faker->numerify('#####'),
        ]);
    }

    /**
     * Reconcile to a small, deterministic bank list (idempotent).
     */
    private function seedBanks(Generator $faker, CompanySite $companySite, int $sequence): void
    {
        $companySite->banks()->delete();

        $bankCount = $sequence % 3; // 0, 1 or 2 banks.

        for ($bank = 0; $bank < $bankCount; $bank++) {
            /** @var CompanySiteBank $created */
            $created = $companySite->banks()->create([
                'name' => $faker->company().' Bank',
                'iban' => $faker->iban('IT'),
                'notes' => $faker->optional()->sentence(),
            ]);

            if ($bank === 0) {
                $companySite->update(['default_bank_id' => $created->id]);
            }
        }
    }
}
