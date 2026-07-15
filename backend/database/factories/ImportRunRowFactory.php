<?php

namespace Database\Factories;

use App\Enums\ImportRowStatus;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRunRow>
 */
class ImportRunRowFactory extends Factory
{
    protected $model = ImportRunRow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'row_number' => fake()->unique()->numberBetween(1, 10000),
            'raw_values' => ['full_name' => fake()->name(), 'email' => fake()->safeEmail()],
            'mapped_values' => ['first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'email' => fake()->safeEmail()],
            'extra_values' => null,
            'resolved' => null,
            'status' => ImportRowStatus::Valid,
            'messages' => null,
            'duplicate_of_id' => null,
            'is_edited' => false,
        ];
    }

    /**
     * The row is blocked by a validation error and excluded from commit.
     */
    public function error(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportRowStatus::Error,
            'messages' => ['email is required.'],
        ]);
    }

    /**
     * The row matched an existing record; outcome depends on dedup strategy.
     */
    public function duplicate(int $duplicateOfId): static
    {
        return $this->state(fn (): array => [
            'status' => ImportRowStatus::Duplicate,
            'duplicate_of_id' => $duplicateOfId,
        ]);
    }
}
