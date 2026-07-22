<?php

declare(strict_types=1);

namespace App\Services\Table;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Derives the validation rules for one inline cell edit from its column's
 * declaration (spec 0053, D-6): `type` maps to a base rule, `nullable`
 * decides whether `value: null` is accepted, and the column's own optional
 * `rules` extend the set — no duplicated per-column schema, no rule invented
 * beyond what the catalogue declares.
 *
 * A RELATION column (spec 0054, D-1: `'relation' => ['resource' => ...]`)
 * takes a SEPARATE path (validateRelationValue): the value is the related
 * row's id, not a value of the display `type` (a relation column keeps
 * `type: 'text'` for display/sort/filter, so the plain type-derived `string`
 * rule would wrongly reject the submitted integer id).
 *
 * A `editor: 'select'` column (spec 0055, D-3 — e.g. request-management's
 * `workflow_status`, whose real field is `opportunity_workflow_status_id` but
 * is not `/for-select`-backed) takes the SAME "value is an id" path minus the
 * for-select scope check: its own domain membership rule (e.g. 0054 AC-011's
 * resolved-workflow set) lives in the definition's `updateCell()` override,
 * not here.
 *
 * That branch keys on `editor`, NOT on the mere presence of `editableField`
 * (spec 0055, D-3): `editableField` only remaps the permission/write key
 * (0054, D-1), and a TEXT column may legitimately use it — e.g.
 * `first_name` -> `client_first_name`, whose value is a string and would be
 * wrongly rejected by an `integer` rule.
 */
final class CellValueValidator
{
    /** The `editor` kind whose submitted value is a related row's id, not a value of the display `type`. */
    private const string SELECT_EDITOR = 'select';

    public function __construct(private readonly RelationValueScopeChecker $relationScope) {}

    /**
     * @param  array<string, mixed>  $column  the raw column declaration (id/type/nullable/rules/options/relation/editableField)
     *
     * @throws ValidationException
     */
    public function validate(array $column, mixed $value, User $actor): mixed
    {
        if (isset($column['relation'])) {
            return $this->validateRelationValue($column, $value, $actor);
        }

        if (($column['editor'] ?? null) === self::SELECT_EDITOR) {
            return $this->validateIdValue($column, $value);
        }

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
     * D-2: the value must be an integer (or null, when the column declares
     * `nullable`) that the actor could actually pick from the declared
     * relation resource's own `/for-select` query — an id that merely
     * `exists` elsewhere is not enough (see RelationValueScopeChecker).
     *
     * @param  array<string, mixed>  $column
     *
     * @throws ValidationException
     */
    private function validateRelationValue(array $column, mixed $value, User $actor): mixed
    {
        $nullable = ($column['nullable'] ?? false) === true;

        Validator::make(
            ['value' => $value],
            ['value' => [$nullable ? 'nullable' : 'required', 'integer']],
        )->validate();

        if ($value === null) {
            return null;
        }

        $resource = $column['relation']['resource'] ?? null;

        if (! is_string($resource) || ! $this->relationScope->inScope($resource, (int) $value, $actor)) {
            throw ValidationException::withMessages([
                'value' => ['The selected value does not exist or is not available.'],
            ]);
        }

        return (int) $value;
    }

    /**
     * A non-relation `select` column's value is still an id, not a value of
     * the display `type` — structural check only (integer, or null when
     * `nullable`); the domain-specific membership rule is the definition's
     * own responsibility (updateCell()). Deliberately NOT an `in:` rule over
     * the column's resolved options: those come from `optionsFor()` (absent
     * from the raw declaration this validator receives), and the AUTHORITATIVE
     * check is per-ROW anyway — a domain-wide catalogue check would be
     * strictly weaker and a second, divergable copy of it.
     *
     * @param  array<string, mixed>  $column
     *
     * @throws ValidationException
     */
    private function validateIdValue(array $column, mixed $value): mixed
    {
        $nullable = ($column['nullable'] ?? false) === true;

        Validator::make(
            ['value' => $value],
            ['value' => [$nullable ? 'nullable' : 'required', 'integer']],
        )->validate();

        return $value === null ? null : (int) $value;
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
