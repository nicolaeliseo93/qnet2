<?php

namespace Database\Factories;

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
        ];
    }

    /**
     * Marks the row as a system status ('new' or 'closed'). Takes the
     * literal system_key string rather than the App\Enums\StatusSystemKey
     * case (backend ownership, not yet landed) so this factory has no
     * dependency on that class.
     */
    public function system(string $key): static
    {
        return $this->state(fn () => [
            'system_key' => $key,
            'name' => $key === 'new' ? 'Nuovo' : 'Chiuso',
            'color' => $key === 'new' ? 'slate' : 'green',
            'sort_order' => $key === 'new' ? 0 : 999,
        ]);
    }

    public function withGroup(int $statusGroupId): static
    {
        return $this->state(fn () => ['status_group_id' => $statusGroupId]);
    }
}
