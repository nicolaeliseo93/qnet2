<?php

declare(strict_types=1);

use App\CustomFields\Types\TextFieldType;
use App\Models\CustomFieldDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

// AC-004: TextFieldType minLength/maxLength/regex + transform in normalizeForStore.
it('builds min/max/regex/required rules from config+validation', function (): void {
    $definition = CustomFieldDefinition::factory()->make([
        'type' => 'text',
        'config' => ['minLength' => 3, 'maxLength' => 5, 'regex' => '/^[a-z]+$/'],
        'validation' => ['required' => true],
    ]);

    $rules = (new TextFieldType)->validationRules($definition);

    expect(Validator::make(['value' => 'abcd'], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 'ab'], ['value' => $rules])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 'abcdef'], ['value' => $rules])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 'AB1'], ['value' => $rules])->fails())->toBeTrue()
        ->and(Validator::make([], ['value' => $rules])->fails())->toBeTrue();
});

it('is nullable when validation.required is absent', function (): void {
    $definition = CustomFieldDefinition::factory()->make(['type' => 'text', 'config' => [], 'validation' => []]);

    $rules = (new TextFieldType)->validationRules($definition);

    expect(Validator::make([], ['value' => $rules])->passes())->toBeTrue();
});

it('applies the configured transform on write only', function (string $transform, string $input, string $expected): void {
    $definition = CustomFieldDefinition::factory()->make(['config' => ['transform' => $transform]]);
    $handler = new TextFieldType;

    expect($handler->normalizeForStore($input, $definition))->toBe($expected)
        ->and($handler->resolveForRead($expected, $definition))->toBe($expected);
})->with([
    'uppercase' => ['uppercase', 'hello', 'HELLO'],
    'lowercase' => ['lowercase', 'HELLO', 'hello'],
    'capitalize' => ['capitalize', 'hello world', 'Hello World'],
]);

it('normalizeForStore passes null through untouched', function (): void {
    $definition = CustomFieldDefinition::factory()->make();

    expect((new TextFieldType)->normalizeForStore(null, $definition))->toBeNull();
});

it('exposes type/config in toMeta', function (): void {
    $definition = CustomFieldDefinition::factory()->make(['config' => ['maxLength' => 10]]);

    expect((new TextFieldType)->toMeta($definition))->toBe([
        'type' => 'text',
        'config' => ['maxLength' => 10],
    ]);
});
