<?php

declare(strict_types=1);

namespace App\Services\Table;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Derives the validation rules for one inline cell edit from its column's
 * declaration (spec 0053, D-6): `type` maps to a base rule, `nullable`
 * decides whether `value: null` is accepted, and the column's own optional
 * `rules` extend the set — no duplicated per-column schema, no rule invented
 * beyond what the catalogue declares.
 */
final class CellValueValidator
{
    /**
     * @param  array<string, mixed>  $column  the raw column declaration (id/type/nullable/rules/options)
     *
     * @throws ValidationException
     */
    public function validate(array $column, mixed $value): mixed
    {
        $nullable = ($column['nullable'] ?? false) === true;

        $rules = [
            $nullable ? 'nullable' : 'required',
            ...$this->typeRules($column),
            ...($column['rules'] ?? []),
        ];

        Validator::make(['value' => $value], ['value' => $rules])->validate();

        return $value;
    }

    /**
     * @param  array<string, mixed>  $column
     * @return array<int, mixed>
     */
    private function typeRules(array $column): array
    {
        return match ($column['type'] ?? null) {
            'text' => ['string'],
            'number' => ['numeric'],
            'boolean' => ['boolean'],
            'date', 'datetime' => ['date'],
            'enum', 'badge' => [Rule::in($column['options'] ?? [])],
            default => [],
        };
    }
}
