<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\OperationalSite;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the operational-sites module (Sedi operative, spec
 * 0011).
 *
 * Each site owns exactly ONE primary address (the module's single-address
 * invariant), tied to a REAL seeded City — picked round-robin from a random
 * sample so consecutive sites rarely share the same comune — so the derived
 * geo columns (comune/via/provincia/regione) have distinct, realistic values
 * to filter/sort/search on (spec 0011's OperationalSiteGeoColumns). Reuses
 * OperationalSiteFactory::withAddress(), which already falls back to a
 * freshly-built City (via its own factory chain) when none is passed — a
 * REAL city either way — mirroring DemoUserAddressSeeder's "no city available"
 * fallback without ever leaving a site geo-less.
 *
 * Deterministic faker seed for reproducibility; existing sites are cleared at
 * the start of the run (idempotent across repeated `db:seed` — deleting a
 * site cascades its address via HasAddresses), mirroring DemoCompanySeeder.
 */
class DemoOperationalSiteSeeder extends Seeder
{
    private const int SITES = 40;

    private const int CITY_SAMPLE = 200;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260703);

        // Idempotent across repeated db:seed: per-model delete (not a mass
        // query delete) so HasAddresses' deleting hook actually fires and
        // cascades each site's address.
        OperationalSite::query()->get()->each(fn (OperationalSite $site) => $site->delete());

        $cities = City::query()
            ->with(['country', 'state', 'province'])
            ->inRandomOrder()
            ->limit(self::CITY_SAMPLE)
            ->get();

        for ($index = 0; $index < self::SITES; $index++) {
            $this->seedSite($faker, $cities, $index);
        }
    }

    /**
     * @param  Collection<int, City>  $cities
     */
    private function seedSite(Generator $faker, Collection $cities, int $index): void
    {
        $city = $cities->isNotEmpty() ? $cities[$index % $cities->count()] : null;

        $site = OperationalSite::factory()->withAddress($city)->create();
        $address = $site->addresses()->first();

        if ($address === null) {
            return;
        }

        $address->update([
            'line1' => $faker->streetAddress(),
            'postal_code' => $this->postalCode($faker, $address->country?->iso2),
        ]);
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
