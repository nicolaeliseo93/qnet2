<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
{
    protected $model = ImportRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource' => 'stub-widgets',
            'user_id' => User::factory(),
            'status' => ImportStatus::Validating,
            'original_filename' => fake()->word().'.csv',
            'stored_path' => 'imports/'.fake()->uuid().'.csv',
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'imported_rows' => null,
            'error_report_path' => null,
            'preview' => null,
        ];
    }

    /**
     * The run has finished validating and is ready for the user's confirm.
     */
    public function awaitingConfirmation(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportStatus::AwaitingConfirmation,
            'total_rows' => 2,
            'valid_rows' => 1,
            'invalid_rows' => 1,
            'preview' => [
                'columns' => ['name', 'type'],
                'valid_sample' => [['name' => 'Sales', 'type' => '']],
                'invalid_sample' => [['row_number' => 2, 'values' => ['name' => '', 'type' => ''], 'errors' => ['name is required.']]],
            ],
        ]);
    }
}
