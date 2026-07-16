<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    /**
     * Default: the 5 mandatory columns (D-4, amendment rev.1 A-2); every
     * other (optional) relation stays null.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' deal',
            'registry_id' => Registry::factory(),
            'company_id' => Company::factory(),
            'company_site_id' => CompanySite::factory(),
            'operational_site_id' => OperationalSite::factory(),
            'business_function_id' => null,
            'referent_id' => null,
            'commercial_id' => null,
            'reporter_id' => null,
            'supervisor_id' => null,
            'source_id' => null,
            'product_category_id' => null,
            'lead_id' => null,
            'start_date' => fake()->optional()->date(),
            'estimated_value' => fake()->optional()->randomFloat(2, 500, 50000),
            'expected_close_date' => fake()->optional()->date(),
            'success_probability' => fake()->optional()->numberBetween(0, 100),
        ];
    }
}
