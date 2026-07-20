<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Classification coherence (spec 0023 REV) for a STANDALONE campaign: the
 * product category's EFFECTIVE business function (own or inherited) must equal
 * the submitted business function. A LINKED campaign derives its classification
 * from the project (fields `prohibited`, BR-2), so the check never applies to
 * it — covered by the existing linked-campaign tests.
 */
if (! function_exists('campaignCoherenceUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function campaignCoherenceUserWith(array $abilities): User
    {
        foreach (['create', 'update'] as $ability) {
            Permission::findOrCreate("campaigns.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("campaigns.{$ability}");
        }

        return $user;
    }
}

it('create: standalone 422 when the product category belongs to a different business function', function () {
    $actor = campaignCoherenceUserWith(['create']);
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryOfB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'project_id' => null,
        'name' => 'Mismatch',
        'business_function_id' => $functionA->id,
        'product_category_id' => $categoryOfB->id,
        'country_id' => Country::factory()->create()->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ])->assertStatus(422)->assertJsonValidationErrors('product_category_id');

    expect(Campaign::count())->toBe(0);
});

it('create: standalone 201 when the category INHERITS the business function from an ancestor', function () {
    $actor = campaignCoherenceUserWith(['create']);
    $function = BusinessFunction::factory()->create();
    $parent = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $child = ProductCategory::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'project_id' => null,
        'name' => 'Inherited',
        'business_function_id' => $function->id,
        'product_category_id' => $child->id,
        'country_id' => Country::factory()->create()->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ])->assertCreated()->assertJsonPath('data.product_category_id', $child->id);
});

it('update: standalone 422 when changing the product category to one of a different business function', function () {
    $actor = campaignCoherenceUserWith(['update']);
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryOfA = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $categoryOfB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    $campaign = Campaign::factory()->create([
        'business_function_id' => $functionA->id,
        'product_category_id' => $categoryOfA->id,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['product_category_id' => $categoryOfB->id])
        ->assertStatus(422)->assertJsonValidationErrors('product_category_id');
});
