<?php

declare(strict_types=1);

use App\CustomFields\Types\EnumFieldType;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeEnumDefinition(array $overrides = []): CustomFieldDefinition
{
    $definition = CustomFieldDefinition::factory()->create(array_merge([
        'type' => 'enum',
        'config' => ['display' => 'select'],
    ], $overrides));

    CustomFieldOption::factory()->create(['definition_id' => $definition->id, 'value' => 'red', 'label' => 'Red', 'sort_order' => 0]);
    CustomFieldOption::factory()->create(['definition_id' => $definition->id, 'value' => 'blue', 'label' => 'Blue', 'sort_order' => 1]);

    return $definition->load('options');
}

// AC-004: EnumFieldType::validationRules imposes value in options (single).
it('rejects a value outside the option set (single-select)', function (): void {
    $definition = makeEnumDefinition();
    $rules = (new EnumFieldType)->validationRules($definition);

    expect(Validator::make(['value' => 'red'], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 'green'], ['value' => $rules])->fails())->toBeTrue();
});

// AC-004: multiselect => array, each element in options.
it('rejects an array with any value outside the option set (multiselect)', function (): void {
    $definition = makeEnumDefinition(['config' => ['display' => 'multiselect']]);
    $rules = (new EnumFieldType)->validationRules($definition);

    expect(Validator::make(['value' => ['red', 'blue']], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => ['red', 'green']], ['value' => $rules])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 'red'], ['value' => $rules])->fails())->toBeTrue();
});

it('normalizes single vs multi and exposes options via toMeta', function (): void {
    $single = makeEnumDefinition();
    $multi = makeEnumDefinition(['config' => ['display' => 'multiselect']]);
    $handler = new EnumFieldType;

    expect($handler->normalizeForStore('red', $single))->toBe('red')
        ->and($handler->normalizeForStore(['red', 'blue'], $multi))->toBe(['red', 'blue'])
        ->and($handler->normalizeForStore(null, $single))->toBeNull();

    $meta = $handler->toMeta($single);

    expect($meta['type'])->toBe('enum')
        ->and($meta['options'])->toBe([
            ['value' => 'red', 'label' => 'Red', 'color' => null, 'icon' => null],
            ['value' => 'blue', 'label' => 'Blue', 'color' => null, 'icon' => null],
        ]);
});
