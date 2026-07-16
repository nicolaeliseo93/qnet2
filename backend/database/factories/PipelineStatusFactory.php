<?php

namespace Database\Factories;

use App\Enums\StatusGroup;
use App\Models\PipelineStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStatus>
 */
class PipelineStatusFactory extends Factory
{
    protected $model = PipelineStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->optional()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 100),
            'group' => StatusGroup::Open,
        ];
    }

    /**
     * Marks the row as a system status ('new' or 'closed').
     */
    public function system(string $key): static
    {
        return $this->state(fn () => [
            'system_key' => $key,
            'name' => $key === 'new' ? 'Nuovo' : 'Chiuso',
            'color' => $key === 'new' ? 'slate' : 'green',
            'sort_order' => $key === 'new' ? 0 : 999,
            'group' => $key === 'new' ? StatusGroup::Open : StatusGroup::Closed,
        ]);
    }

    public function group(StatusGroup $group): static
    {
        return $this->state(fn () => ['group' => $group]);
    }
}
