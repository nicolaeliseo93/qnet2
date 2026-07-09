<?php

declare(strict_types=1);

use App\CustomFields\Types\DecimalFieldType;
use App\Models\CustomFieldDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

// AC-004: DecimalFieldType respects min/max/step/decimals.
it('builds min/max rules from config', function (): void {
    $definition = CustomFieldDefinition::factory()->make(['config' => ['min' => 1.5, 'max' => 9.5]]);

    $rules = (new DecimalFieldType)->validationRules($definition);

    expect(Validator::make(['value' => 5.25], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 1.0], ['value' => $rules])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 9.6], ['value' => $rules])->fails())->toBeTrue();
});

it('enforces the configured maximum number of decimal places', function (): void {
    $definition = CustomFieldDefinition::factory()->make(['config' => ['decimals' => 2]]);

    $rules = (new DecimalFieldType)->validationRules($definition);

    expect(Validator::make(['value' => 3.14], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 3.14159], ['value' => $rules])->fails())->toBeTrue();
});

it('enforces the step grid starting at min', function (): void {
    $definition = CustomFieldDefinition::factory()->make(['config' => ['min' => 0, 'step' => 0.5]]);

    $rules = (new DecimalFieldType)->validationRules($definition);

    expect(Validator::make(['value' => 1.5], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 1.3], ['value' => $rules])->fails())->toBeTrue();
});

it('rounds to config.decimals on write and casts to float on read', function (): void {
    $definition = CustomFieldDefinition::factory()->make(['config' => ['decimals' => 2]]);
    $handler = new DecimalFieldType;

    expect($handler->normalizeForStore('3.14159', $definition))->toBe(3.14)
        ->and($handler->normalizeForStore(null, $definition))->toBeNull()
        ->and($handler->resolveForRead('3.14', $definition))->toBe(3.14);
});
