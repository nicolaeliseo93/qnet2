<?php

declare(strict_types=1);

namespace App\CustomFields;

use App\Models\CustomFieldDefinition;
use InvalidArgumentException;

/**
 * Pure SQL string builder for the opt-in index-promotion lane (spec 0021,
 * AC-021, T15) — no DB access here, so every method is unit-testable without
 * a live MySQL connection. Consumed exclusively by
 * App\Jobs\PromoteCustomFieldIndexJob, the only caller allowed to execute the
 * statements this class returns.
 *
 * The scalar generated-column expression (jsonPathExpression()) is the SAME
 * string Laravel's MySQL query grammar compiles for
 * `where('custom_field_values.values->{key}', ...)` / `orderBy(...)` — see
 * App\CustomFields\Types\Concerns\ResolvesJsonColumn (the handlers' shared
 * jsonColumn() builder) and
 * Illuminate\Database\Query\Grammars\MySqlGrammar::wrapJsonSelector() +
 * Illuminate\Database\Concerns\CompilesJsonPaths — minus the
 * `custom_field_values.` table qualifier, which MySQL does not allow inside a
 * generated column's own expression. MySQL's functional-index matcher
 * compares the RESOLVED expression tree (same column, same functions), not
 * the raw qualified/unqualified text, so this still lets the existing
 * FieldTypeHandler filter/sort queries use the index with zero handler
 * changes — verify with EXPLAIN on a real MySQL/MariaDB instance (see
 * App\Jobs\PromoteCustomFieldIndexJob's docblock for the exact query).
 */
final class CustomFieldIndexDdlBuilder
{
    public const string TABLE = 'custom_field_values';

    private const string COLUMN_PREFIX = 'cfg_';

    private const string INDEX_PREFIX = 'idx_';

    /** MySQL identifier length limit — applies to both column and index names. */
    private const int MAX_IDENTIFIER_LENGTH = 64;

    private const string KEY_PATTERN = '/^[a-z0-9_]+$/';

    /**
     * `cfg_<key>`, truncated with a short content hash when the key is long
     * enough to exceed MySQL's identifier limit. Deterministic (same key ->
     * same column) so re-running the job stays idempotent even before the
     * `Schema::hasColumn()` guard is consulted.
     */
    public function columnName(string $key): string
    {
        $this->guardKey($key);

        $candidate = self::COLUMN_PREFIX.$key;

        if (mb_strlen($candidate) <= self::MAX_IDENTIFIER_LENGTH) {
            return $candidate;
        }

        $hash = substr(md5($key), 0, 8);
        $budget = self::MAX_IDENTIFIER_LENGTH - mb_strlen(self::COLUMN_PREFIX) - mb_strlen($hash) - 1;

        return self::COLUMN_PREFIX.mb_substr($key, 0, $budget).'_'.$hash;
    }

    /**
     * `idx_<column>`, hash-shortened under the same identifier-length limit.
     */
    public function indexName(string $column): string
    {
        $candidate = self::INDEX_PREFIX.$column;

        if (mb_strlen($candidate) <= self::MAX_IDENTIFIER_LENGTH) {
            return $candidate;
        }

        return self::INDEX_PREFIX.substr(md5($column), 0, 16);
    }

    /**
     * The scalar JSON-path expression, UNQUALIFIED (see class docblock).
     */
    public function jsonPathExpression(string $key): string
    {
        $this->guardKey($key);

        return "json_unquote(json_extract(`values`, '$.\"{$key}\"'))";
    }

    /**
     * The RAW (non-unquoted) JSON-path expression a multi-valued index is
     * built on: MySQL's `CAST(... AS type ARRAY)` multi-valued index
     * requires the JSON array itself, not its unquoted scalar serialization.
     */
    public function jsonArrayPathExpression(string $key): string
    {
        $this->guardKey($key);

        return "json_extract(`values`, '$.\"{$key}\"')";
    }

    /**
     * SQL type for the scalar generated column. Derived directly from the
     * definition's own `type` (mirrors the type-specific checks already used
     * in App\Services\CustomFieldService) rather than FieldTypeHandler::
     * storageType(), which is ambiguous here: `relation` reports `json`
     * regardless of cardinality, but a single (cardinality=one) relation
     * value is always an integer id.
     */
    public function scalarSqlType(CustomFieldDefinition $definition): string
    {
        return match ($definition->type) {
            'integer' => 'BIGINT',
            'decimal' => 'DECIMAL(20,6)',
            'relation' => 'BIGINT',
            // boolean MUST be a string type: the generated column expression is
            // `json_unquote(json_extract(...))` (to match Laravel's WHERE so the
            // index is usable), and json_unquote of a JSON boolean yields the
            // string 'true'/'false' — which a TINYINT rejects on INSERT
            // ("Incorrect integer value: 'true'"), breaking every write to the
            // row. VARCHAR stores it and still serves boolean equality filters.
            default => 'VARCHAR(191)', // text, textarea, enum (scalar), boolean
        };
    }

    /**
     * MySQL 8.0.17+ multi-valued index array element type: relation ids are
     * unsigned integers, everything else (multiselect enum values) is text.
     */
    public function multiValuedElementType(CustomFieldDefinition $definition): string
    {
        return $definition->type === 'relation' ? 'UNSIGNED' : 'CHAR(191)';
    }

    public function addGeneratedColumnStatement(string $column, string $sqlType, string $key): string
    {
        $expression = $this->jsonPathExpression($key);

        return sprintf(
            'alter table `%s` add column `%s` %s generated always as (%s) stored',
            self::TABLE,
            $column,
            $sqlType,
            $expression,
        );
    }

    public function addIndexStatement(string $index, string $column): string
    {
        return sprintf('alter table `%s` add index `%s` (`%s`)', self::TABLE, $index, $column);
    }

    public function addMultiValuedIndexStatement(string $index, string $key, string $elementType): string
    {
        $expression = $this->jsonArrayPathExpression($key);

        return sprintf(
            'alter table `%s` add index `%s` ((cast(%s as %s array)))',
            self::TABLE,
            $index,
            $expression,
            $elementType,
        );
    }

    public function dropIndexStatement(string $index): string
    {
        return sprintf('alter table `%s` drop index `%s`', self::TABLE, $index);
    }

    public function dropColumnStatement(string $column): string
    {
        return sprintf('alter table `%s` drop column `%s`', self::TABLE, $column);
    }

    /**
     * Defense in depth: `key` is already constrained to `/^[a-z0-9_]+$/` by
     * StoreCustomFieldRequest/UpdateCustomFieldRequest before it can ever
     * reach a persisted definition, but this class builds raw DDL strings —
     * refuse anything that would not be safe to interpolate.
     */
    private function guardKey(string $key): void
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("Refusing to build index DDL for an unsafe custom field key: \"{$key}\".");
        }
    }
}
