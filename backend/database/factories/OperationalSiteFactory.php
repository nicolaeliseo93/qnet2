<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\City;
use App\Models\OperationalSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalSite>
 */
class OperationalSiteFactory extends Factory
{
    protected $model = OperationalSite::class;

    /**
     * The site itself carries no own columns beyond id/timestamps (spec
     * 0011): its identity lives entirely on the primary address.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }

    /**
     * Attach a primary address to the site, tied to a REAL City (with its
     * full country/state ancestry) so geo-derived columns/filters have
     * something meaningful to resolve in tests/seeders.
     */
    public function withAddress(?City $city = null): static
    {
        return $this->afterCreating(function (OperationalSite $site) use ($city): void {
            Address::factory()->primary()->forCity($city ?? City::factory()->create())
                ->for($site, 'addressable')->create();
        });
    }
}
