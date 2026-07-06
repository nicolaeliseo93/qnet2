<?php

namespace Database\Factories;

use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\ReferentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referent>
 */
class ReferentFactory extends Factory
{
    protected $model = Referent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'referent_type_id' => ReferentType::factory(),
            'contact_scope' => fake()->randomElement(['internal', 'external']),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * No classification (referent_type_id stays null, nullOnDelete default).
     */
    public function withoutType(): static
    {
        return $this->state(fn (): array => [
            'referent_type_id' => null,
        ]);
    }

    /**
     * Attach a personal-data card (morph `personable`, same pattern used by
     * `PersonalDataSeeder` for users: `$owner->personalData()->updateOrCreate()`
     * / `PersonalData::factory()->for($owner, 'personable')`), then re-derive
     * `name` from the card's display name so it stays consistent with the
     * denormalized `referents.name` column (mirrors `users.name`).
     */
    public function withPersonalData(?callable $factory = null): static
    {
        return $this->afterCreating(function (Referent $referent) use ($factory): void {
            $card = PersonalData::factory();
            $card = $factory !== null ? $factory($card) : $card;

            /** @var PersonalData $card */
            $card = $card->for($referent, 'personable')->create();

            $referent->forceFill(['name' => $card->full_name])->save();
        });
    }
}
