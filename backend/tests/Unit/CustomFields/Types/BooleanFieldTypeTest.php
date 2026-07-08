<?php

declare(strict_types=1);

use App\CustomFields\Types\BooleanFieldType;
use App\Models\CustomFieldDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

it('validates a boolean value and rejects a non-boolean one', function (): void {
    $definition = CustomFieldDefinition::factory()->make();
    $rules = (new BooleanFieldType)->validationRules($definition);

    expect(Validator::make(['value' => true], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 'nope'], ['value' => $rules])->fails())->toBeTrue();
});

it('normalizes to bool and passes config through toMeta', function (): void {
    $definition = CustomFieldDefinition::factory()->make(['config' => ['display' => 'switch']]);
    $handler = new BooleanFieldType;

    expect($handler->normalizeForStore('1', $definition))->toBeTrue()
        ->and($handler->normalizeForStore(null, $definition))->toBeNull()
        ->and($handler->resolveForRead(true, $definition))->toBeTrue()
        ->and($handler->toMeta($definition))->toBe(['type' => 'boolean', 'config' => ['display' => 'switch']]);
});
