<?php

namespace Database\Factories;

use App\Models\ImportMappingTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportMappingTemplate>
 */
class ImportMappingTemplateFactory extends Factory
{
    protected $model = ImportMappingTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource' => 'leads',
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'columns' => ['Nome', 'Email', 'Telefono'],
            'column_mapping' => [
                'Nome' => 'name',
                'Email' => 'email',
                'Telefono' => 'phone',
            ],
            'dedup_strategy' => null,
        ];
    }
}
