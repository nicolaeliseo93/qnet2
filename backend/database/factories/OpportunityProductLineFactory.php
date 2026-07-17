<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityProductLine>
 */
class OpportunityProductLineFactory extends Factory
{
    protected $model = OpportunityProductLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'business_function_id' => BusinessFunction::factory(),
            'product_category_id' => ProductCategory::factory(),
        ];
    }
}
