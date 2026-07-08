<?php

declare(strict_types=1);

use App\CustomFields\Types\IntegerFieldType;
use App\Models\CustomFieldDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

// AC-004: IntegerFieldType respects min/max/step.
it('builds min/max rules from config', function (): void {
    $definition = CustomFieldDefinition::factory()->make(['config' => ['min' => 10, 'max' => 20]]);

    $rules = (new IntegerFieldType)->validationRules($definition);

    expect(Validator::make(['value' => 15], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 5], ['value' => $rules])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 25], ['value' => $rules])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 'abc'], ['value' => $rules])->fails())->toBeTrue();
});

it('enforces the step grid starting at min', function (): void {
    $definition = CustomFieldDefinition::factory()->make(['config' => ['min' => 0, 'step' => 5]]);

    $rules = (new IntegerFieldType)->validationRules($definition);

    expect(Validator::make(['value' => 15], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 7], ['value' => $rules])->fails())->toBeTrue();
});

it('normalizes to an int, rounding a numeric string', function (): void {
    $definition = CustomFieldDefinition::factory()->make();
    $handler = new IntegerFieldType;

    expect($handler->normalizeForStore('7', $definition))->toBe(7)
        ->and($handler->normalizeForStore(7.6, $definition))->toBe(8)
        ->and($handler->normalizeForStore(null, $definition))->toBeNull()
        ->and($handler->resolveForRead(7, $definition))->toBe(7);
});
