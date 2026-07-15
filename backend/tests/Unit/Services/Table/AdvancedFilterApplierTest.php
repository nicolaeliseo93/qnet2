<?php

use App\Enums\AdvancedFilterType;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\Table\AdvancedFilterApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Touches the database (query application), so bind the full TestCase +
// RefreshDatabase explicitly (Unit suite has no default RefreshDatabase binding).
uses(TestCase::class, RefreshDatabase::class);

/** A descriptor shaped like a catalog entry, defaults overridable per test. */
function advancedFilterDescriptor(array $overrides = []): array
{
    return array_merge([
        'name' => 'field',
        'label' => 'Field',
        'type' => AdvancedFilterType::Text,
        'order' => 1,
        'required' => false,
        'visible' => true,
        'width' => 'md',
        'multiple' => false,
        'target' => 'name',
    ], $overrides);
}

it('applies a text filter as a bound, escaped LIKE contains by default', function () {
    BusinessFunction::factory()->create(['name' => 'Sales Ops']);
    BusinessFunction::factory()->create(['name' => 'Support']);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply($query, AdvancedFilterType::Text, 'name', 'Sales', []);

    expect($query->pluck('name')->all())->toBe(['Sales Ops']);
});

it('applies a text filter injection payload as a literal bound value (AC-005)', function () {
    BusinessFunction::factory()->create(['name' => 'Sales Ops']);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply($query, AdvancedFilterType::Text, 'name', "' OR 1=1--", []);

    // Treated as a literal substring: matches nothing, no SQL error, no widening.
    expect($query->count())->toBe(0);
});

it('honours the internal `operator` override for a text filter', function () {
    BusinessFunction::factory()->create(['name' => 'Sales']);
    BusinessFunction::factory()->create(['name' => 'Sales Ops']);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply($query, AdvancedFilterType::Text, 'name', 'Sales', ['operator' => 'equals']);

    expect($query->pluck('name')->all())->toBe(['Sales']);
});

it('applies a number_range filter as whereBetween/gte/lte', function () {
    $one = BusinessFunction::factory()->create(['name' => 'A']);
    $two = BusinessFunction::factory()->create(['name' => 'B']);
    BusinessFunction::factory()->create(['name' => 'C']);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply(
        $query, AdvancedFilterType::NumberRange, 'id', ['from' => $one->id, 'to' => $two->id], [],
    );

    expect($query->pluck('id')->all())->toBe([$one->id, $two->id]);
});

it('applies a number_range filter with only a lower bound as gte', function () {
    $one = BusinessFunction::factory()->create();
    $two = BusinessFunction::factory()->create();

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply($query, AdvancedFilterType::NumberRange, 'id', ['from' => $two->id], []);

    expect($query->pluck('id')->all())->toBe([$two->id]);
});

it('applies a date_range filter as whereBetween on the date bounds', function () {
    $inRange = BusinessFunction::factory()->create(['name' => 'InRange']);
    $inRange->forceFill(['created_at' => '2026-06-15'])->save();

    $outOfRange = BusinessFunction::factory()->create(['name' => 'OutOfRange']);
    $outOfRange->forceFill(['created_at' => '2026-01-01'])->save();

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply(
        $query, AdvancedFilterType::DateRange, 'created_at', ['from' => '2026-06-01', 'to' => '2026-06-30'], [],
    );

    expect($query->pluck('name')->all())->toBe(['InRange']);
});

it('applies a checkbox filter as a bound boolean equality', function () {
    BusinessFunction::factory()->create(['name' => 'Unit', 'is_business_unit' => true]);
    BusinessFunction::factory()->create(['name' => 'NotUnit', 'is_business_unit' => false]);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply($query, AdvancedFilterType::Checkbox, 'is_business_unit', true, []);

    expect($query->pluck('name')->all())->toBe(['Unit']);
});

it('applies a multiselect filter as a bound whereIn, capping non-scalar entries out', function () {
    BusinessFunction::factory()->create(['name' => 'Alpha']);
    BusinessFunction::factory()->create(['name' => 'Beta']);
    BusinessFunction::factory()->create(['name' => 'Gamma']);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply(
        $query, AdvancedFilterType::Multiselect, 'name', ['Alpha', 'Beta', ['nested' => 'ignored']], [],
    );

    expect($query->pluck('name')->all())->toBe(['Alpha', 'Beta']);
});

it('applies a single relation filter as whereHas matched on the related key (generic, no domain code)', function () {
    $managerA = User::factory()->create();
    $managerB = User::factory()->create();
    BusinessFunction::factory()->withManager($managerA)->create(['name' => 'Under A']);
    BusinessFunction::factory()->withManager($managerB)->create(['name' => 'Under B']);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply(
        $query, AdvancedFilterType::Relation, 'manager', $managerA->id, ['multiple' => false],
    );

    expect($query->pluck('name')->all())->toBe(['Under A']);
});

it('applies a multi relation filter as whereHas + whereIn on the related key', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    BusinessFunction::factory()->withUsers(users: [$userA])->create(['name' => 'WithA']);
    BusinessFunction::factory()->withUsers(users: [$userB])->create(['name' => 'WithB']);
    BusinessFunction::factory()->withUsers(users: [$userC])->create(['name' => 'WithC']);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply(
        $query, AdvancedFilterType::Relation, 'users', [$userA->id, $userB->id], ['multiple' => true],
    );

    expect($query->pluck('name')->all())->toBe(['WithA', 'WithB']);
});

it('applies a single async_search filter as whereHas matched on the related key, id-based like relation', function () {
    $managerA = User::factory()->create();
    $managerB = User::factory()->create();
    BusinessFunction::factory()->withManager($managerA)->create(['name' => 'Under A']);
    BusinessFunction::factory()->withManager($managerB)->create(['name' => 'Under B']);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply(
        $query, AdvancedFilterType::AsyncSearch, 'manager', $managerA->id, ['multiple' => false],
    );

    expect($query->pluck('name')->all())->toBe(['Under A']);
});

it('applies a multi async_search filter as whereHas + whereIn on the related key', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    BusinessFunction::factory()->withUsers(users: [$userA])->create(['name' => 'WithA']);
    BusinessFunction::factory()->withUsers(users: [$userB])->create(['name' => 'WithB']);
    BusinessFunction::factory()->withUsers(users: [$userC])->create(['name' => 'WithC']);

    $query = BusinessFunction::query();
    app(AdvancedFilterApplier::class)->apply(
        $query, AdvancedFilterType::AsyncSearch, 'users', [$userA->id, $userB->id], ['multiple' => true],
    );

    expect($query->pluck('name')->all())->toBe(['WithA', 'WithB']);
});

it('validates: async_search accepts an id (single) and an array of ids (multiple), like relation', function () {
    $catalog = [
        'manager' => advancedFilterDescriptor(['name' => 'manager', 'type' => AdvancedFilterType::AsyncSearch]),
        'users' => advancedFilterDescriptor(['name' => 'users', 'type' => AdvancedFilterType::AsyncSearch, 'multiple' => true]),
    ];

    $errors = app(AdvancedFilterApplier::class)->validate($catalog, ['manager' => 1, 'users' => [1, 2]]);

    expect($errors)->toBe([]);
});

it('validates: async_search (multiple) rejects a bare scalar, requiring an array of ids', function () {
    $catalog = ['users' => advancedFilterDescriptor(['name' => 'users', 'type' => AdvancedFilterType::AsyncSearch, 'multiple' => true])];

    $errors = app(AdvancedFilterApplier::class)->validate($catalog, ['users' => 1]);

    expect($errors)->toHaveKey('users');
});

it('validates: rejects a key outside the catalog allow-list', function () {
    $errors = app(AdvancedFilterApplier::class)->validate(
        ['name' => advancedFilterDescriptor()],
        ['ghost' => 'x'],
    );

    expect($errors)->toHaveKey('ghost');
});

it('validates: rejects a mistyped value for a known filter', function () {
    $catalog = ['id_range' => advancedFilterDescriptor(['name' => 'id_range', 'type' => AdvancedFilterType::NumberRange])];

    $errors = app(AdvancedFilterApplier::class)->validate($catalog, ['id_range' => 'not-a-range']);

    expect($errors)->toHaveKey('id_range');
});

it('validates: accepts a well-shaped value per type', function () {
    $catalog = [
        'name' => advancedFilterDescriptor(),
        'id_range' => advancedFilterDescriptor(['name' => 'id_range', 'type' => AdvancedFilterType::NumberRange]),
        'is_unit' => advancedFilterDescriptor(['name' => 'is_unit', 'type' => AdvancedFilterType::Checkbox]),
        'users' => advancedFilterDescriptor(['name' => 'users', 'type' => AdvancedFilterType::Relation, 'multiple' => true]),
    ];

    $errors = app(AdvancedFilterApplier::class)->validate($catalog, [
        'name' => 'Sales',
        'id_range' => ['from' => 1, 'to' => 10],
        'is_unit' => true,
        'users' => [1, 2, 3],
    ]);

    expect($errors)->toBe([]);
});
