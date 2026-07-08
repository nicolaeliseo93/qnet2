<?php

declare(strict_types=1);

use App\CustomFields\Types\RelationFieldType;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

// modelClassFor()/CustomFieldEntityRegistry resolves TableRegistry +
// AuthorizationRegistry definitions, some of which touch the DB.
uses(TestCase::class, RefreshDatabase::class);

// AC-004: RelationFieldType imposes exists on the target (single).
it('rejects a relation id that does not exist on the target table (single)', function (): void {
    $company = Company::factory()->create();

    $definition = CustomFieldDefinition::factory()->make([
        'type' => 'relation',
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => 'companies'],
    ]);

    $rules = app(RelationFieldType::class)->validationRules($definition);

    expect(Validator::make(['value' => $company->id], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => $company->id + 999], ['value' => $rules])->fails())->toBeTrue();
});

// AC-004: array-of-exists (multi).
it('rejects an array with any id missing on the target table (many)', function (): void {
    $companies = Company::factory()->count(2)->create();

    $definition = CustomFieldDefinition::factory()->make([
        'type' => 'relation',
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'many', 'for_select_resource' => 'companies'],
    ]);

    $rules = app(RelationFieldType::class)->validationRules($definition);
    $validIds = $companies->pluck('id')->all();

    expect(Validator::make(['value' => $validIds], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => [$validIds[0], 999999]], ['value' => $rules])->fails())->toBeTrue();
});

it('normalizes single vs many to int/array-of-int and exposes relation meta', function (): void {
    $definitionOne = CustomFieldDefinition::factory()->make([
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => 'companies'],
    ]);
    $definitionMany = CustomFieldDefinition::factory()->make([
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'many', 'for_select_resource' => 'companies'],
    ]);

    $handler = app(RelationFieldType::class);

    expect($handler->normalizeForStore('5', $definitionOne))->toBe(5)
        ->and($handler->normalizeForStore(['1', '2'], $definitionMany))->toBe([1, 2])
        ->and($handler->normalizeForStore(null, $definitionOne))->toBeNull();

    expect($handler->toMeta($definitionOne)['relation'])->toBe([
        'for_select_resource' => 'companies',
        'cardinality' => 'one',
    ]);
});

it('skips the exists rule gracefully when the target entity_type is not resolvable', function (): void {
    $definition = CustomFieldDefinition::factory()->make([
        'relation_target' => ['entity_type' => 'does-not-exist', 'cardinality' => 'one'],
    ]);

    $rules = app(RelationFieldType::class)->validationRules($definition);

    expect(Validator::make(['value' => 123], ['value' => $rules])->passes())->toBeTrue();
});
