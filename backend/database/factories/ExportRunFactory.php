<?php

namespace Database\Factories;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportRun>
 */
class ExportRunFactory extends Factory
{
    protected $model = ExportRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource' => 'stub-exports',
            'user_id' => User::factory(),
            'status' => ExportStatus::Processing,
            'format' => ExportFormat::Csv,
            'original_filename' => 'stub-exports-'.now()->format('Y-m-d').'.csv',
            'state' => [
                'columns' => [['colId' => 'name', 'header' => 'Name']],
                'sortModel' => [],
                'filterModel' => [],
                'search' => null,
            ],
            'file_path' => null,
            'row_count' => null,
        ];
    }

    /**
     * The run finished generating successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Completed,
            'file_path' => 'exports/'.fake()->uuid().'.csv',
            'row_count' => 3,
        ]);
    }

    /**
     * The run failed during generation.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Failed,
        ]);
    }
}
