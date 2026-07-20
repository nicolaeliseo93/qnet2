<?php

use App\Models\BusinessFunction;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Classification coherence (spec 0023 REV): a project's product category must
 * belong to its business function — the category's EFFECTIVE business function
 * (own, or inherited from an ancestor) must equal the submitted one. Enforced
 * server-side by ValidatesProductCategoryBusinessFunction on both create and
 * update, mirroring the product-categories/for-select filter the UI uses.
 */
if (! function_exists('projectCoherenceUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function projectCoherenceUserWith(array $abilities): User
    {
        foreach (['create', 'update'] as $ability) {
            Permission::findOrCreate("projects.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("projects.{$ability}");
        }

        return $user;
    }
}

it('create: 422 when the product category belongs to a different business function', function () {
    $actor = projectCoherenceUserWith(['create']);
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryOfB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Mismatch',
        'pipeline_status_id' => PipelineStatus::factory()->create()->id,
        'country_id' => Country::factory()->create()->id,
        'business_function_id' => $functionA->id,
        'product_category_id' => $categoryOfB->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ])->assertStatus(422)->assertJsonValidationErrors('product_category_id');

    expect(Project::count())->toBe(0);
});

it('create: 201 when the category INHERITS the selected business function from an ancestor', function () {
    $actor = projectCoherenceUserWith(['create']);
    $function = BusinessFunction::factory()->create();
    $parent = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    // The child has no own business function -> its EFFECTIVE one is the parent's.
    $child = ProductCategory::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Inherited',
        'pipeline_status_id' => PipelineStatus::factory()->create()->id,
        'country_id' => Country::factory()->create()->id,
        'business_function_id' => $function->id,
        'product_category_id' => $child->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ])->assertCreated()->assertJsonPath('data.product_category_id', $child->id);
});

it('update: 422 when changing the product category to one of a different business function', function () {
    $actor = projectCoherenceUserWith(['update']);
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryOfA = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $categoryOfB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    $project = Project::factory()->create([
        'business_function_id' => $functionA->id,
        'product_category_id' => $categoryOfA->id,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['product_category_id' => $categoryOfB->id])
        ->assertStatus(422)->assertJsonValidationErrors('product_category_id');
});
