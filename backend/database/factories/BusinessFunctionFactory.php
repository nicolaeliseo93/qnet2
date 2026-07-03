<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends Factory<BusinessFunction>
 */
class BusinessFunctionFactory extends Factory
{
    protected $model = BusinessFunction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Mutually exclusive type (spec 0010): business unit, business
        // service, or neither — never both at once.
        $type = fake()->randomElement(['business_unit', 'business_service', null]);

        return [
            'name' => fake()->unique()->company(),
            'is_business_unit' => $type === 'business_unit',
            'is_business_service' => $type === 'business_service',
            'manager_id' => null,
        ];
    }

    /**
     * Force the exclusive "business unit" type.
     */
    public function businessUnit(): static
    {
        return $this->state(fn (): array => [
            'is_business_unit' => true,
            'is_business_service' => false,
        ]);
    }

    /**
     * Force the exclusive "business service" type.
     */
    public function businessService(): static
    {
        return $this->state(fn (): array => [
            'is_business_unit' => false,
            'is_business_service' => true,
        ]);
    }

    /**
     * Assign a responsible manager: the given user, or a freshly created one.
     */
    public function withManager(?User $manager = null): static
    {
        return $this->state(fn (): array => [
            'manager_id' => $manager?->id ?? User::factory(),
        ]);
    }

    /**
     * Attach associated users after creation. Pass existing users to avoid
     * creating new ones; otherwise $count users are created.
     *
     * @param  Collection<int, User>|array<int, User>|null  $users
     */
    public function withUsers(int $count = 3, Collection|array|null $users = null): static
    {
        return $this->afterCreating(function (BusinessFunction $businessFunction) use ($count, $users): void {
            $members = $users !== null
                ? collect($users)->take($count)
                : User::factory()->count($count)->create();

            $businessFunction->users()->syncWithoutDetaching(
                collect($members)->pluck('id')->all(),
            );
        });
    }
}
