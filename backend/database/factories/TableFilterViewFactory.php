<?php

namespace Database\Factories;

use App\Enums\FilterViewVisibility;
use App\Models\TableFilterView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableFilterView>
 */
class TableFilterViewFactory extends Factory
{
    protected $model = TableFilterView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'domain' => 'users',
            'name' => fake()->unique()->words(2, true),
            'filters' => [],
            'visibility' => FilterViewVisibility::Private->value,
        ];
    }
}
