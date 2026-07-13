<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Referent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * Default: only the 2 mandatory relations (BR-1); the 3 optional ones
     * stay null, mirroring how AC-010 expects a bare create to persist.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referent_id' => Referent::factory(),
            'campaign_id' => Campaign::factory(),
            'operational_site_id' => null,
            'source_id' => null,
            'operator_id' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
