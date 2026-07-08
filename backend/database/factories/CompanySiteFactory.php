<?php

namespace Database\Factories;

use App\Models\CompanySite;
use App\Models\PersonalData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySite>
 */
class CompanySiteFactory extends Factory
{
    protected $model = CompanySite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'notes' => fake()->optional()->sentence(),
            'is_default' => false,
        ];
    }

    /**
     * Attach a personal-data card (morph `personable`, type company) to the
     * site — mirrors RegistryFactory::withPersonalData. Unlike Registry the
     * site's `name` is NOT re-derived from the card (it is the site's own
     * column). Pass a callback to customize the card factory (e.g. add
     * contacts/addresses under the card).
     */
    public function withPersonalData(?callable $factory = null): static
    {
        return $this->afterCreating(function (CompanySite $companySite) use ($factory): void {
            $card = PersonalData::factory()->company();
            $card = $factory !== null ? $factory($card) : $card;

            /** @var PersonalData $card */
            $card->for($companySite, 'personable')->create();
        });
    }

    /**
     * The exclusive default site (see CompanySiteService::setDefault).
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
