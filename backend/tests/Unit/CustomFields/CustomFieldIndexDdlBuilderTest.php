<?php

declare(strict_types=1);

use App\CustomFields\CustomFieldIndexDdlBuilder;
use App\Models\CustomFieldDefinition;

// spec 0021 — T15 (AC-021): the index-promotion DDL builder is pure (no DB
// access), so every statement it would emit for MySQL can be asserted here
// WITHOUT a live MySQL connection. The exact expression asserted below is the
// string documented in App\CustomFields\Types\Concerns\ResolvesJsonColumn /
// Illuminate\Database\Query\Grammars\MySqlGrammar::wrapJsonSelector() as what
// Laravel compiles for `where('custom_field_values.values->{key}', ...)`.

it('builds the scalar generated-column expression matching Laravel\'s compiled JSON where/orderBy', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;

    expect($builder->jsonPathExpression('headcount'))
        ->toBe('json_unquote(json_extract(`values`, \'$."headcount"\'))');
});

it('builds the raw (non-unquoted) array-path expression for multi-valued indexes', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;

    expect($builder->jsonArrayPathExpression('tags'))
        ->toBe('json_extract(`values`, \'$."tags"\')');
});

it('derives a deterministic cfg_<key> column name', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;

    expect($builder->columnName('headcount'))->toBe('cfg_headcount')
        ->and($builder->columnName('headcount'))->toBe($builder->columnName('headcount'));
});

it('truncates+hashes the column name when cfg_<key> would exceed MySQL\'s 64-char identifier limit', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;
    $longKey = str_repeat('a', 64);

    $column = $builder->columnName($longKey);

    expect(mb_strlen($column))->toBeLessThanOrEqual(64)
        ->and($column)->toStartWith('cfg_')
        ->and($column)->toBe($builder->columnName($longKey)); // deterministic
});

it('derives a deterministic idx_<column> index name, hash-shortened past the limit', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;

    expect($builder->indexName('cfg_headcount'))->toBe('idx_cfg_headcount');

    $longColumn = 'cfg_'.str_repeat('b', 62);
    $index = $builder->indexName($longColumn);

    expect(mb_strlen($index))->toBeLessThanOrEqual(64)
        ->and($index)->toStartWith('idx_')
        ->and($index)->toBe($builder->indexName($longColumn));
});

it('rejects a key that is not the allow-listed /^[a-z0-9_]+$/ shape (defense in depth)', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;

    expect(fn () => $builder->jsonPathExpression("evil'; DROP TABLE users; --"))
        ->toThrow(InvalidArgumentException::class);
});

it('maps scalar SQL types per field type', function (string $type, string $expected): void {
    $builder = new CustomFieldIndexDdlBuilder;
    $definition = new CustomFieldDefinition(['type' => $type]);

    expect($builder->scalarSqlType($definition))->toBe($expected);
})->with([
    'integer' => ['integer', 'BIGINT'],
    'decimal' => ['decimal', 'DECIMAL(20,6)'],
    'boolean' => ['boolean', 'TINYINT(1)'],
    'relation (single)' => ['relation', 'BIGINT'],
    'text' => ['text', 'VARCHAR(191)'],
    'enum (scalar)' => ['enum', 'VARCHAR(191)'],
]);

it('maps the multi-valued array element type: UNSIGNED for relation ids, CHAR(191) otherwise', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;

    expect($builder->multiValuedElementType(new CustomFieldDefinition(['type' => 'relation'])))->toBe('UNSIGNED')
        ->and($builder->multiValuedElementType(new CustomFieldDefinition(['type' => 'enum'])))->toBe('CHAR(191)');
});

it('builds the full ADD COLUMN + ADD INDEX statements for a scalar field', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;

    expect($builder->addGeneratedColumnStatement('cfg_headcount', 'BIGINT', 'headcount'))
        ->toBe('alter table `custom_field_values` add column `cfg_headcount` BIGINT generated always as (json_unquote(json_extract(`values`, \'$."headcount"\'))) stored')
        ->and($builder->addIndexStatement('idx_cfg_headcount', 'cfg_headcount'))
        ->toBe('alter table `custom_field_values` add index `idx_cfg_headcount` (`cfg_headcount`)');
});

it('builds the multi-valued ADD INDEX statement with CAST(... AS type ARRAY)', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;

    expect($builder->addMultiValuedIndexStatement('idx_cfg_tags', 'tags', 'CHAR(191)'))
        ->toBe('alter table `custom_field_values` add index `idx_cfg_tags` ((cast(json_extract(`values`, \'$."tags"\') as CHAR(191) array)))');
});

it('builds the reversible DROP statements', function (): void {
    $builder = new CustomFieldIndexDdlBuilder;

    expect($builder->dropIndexStatement('idx_cfg_headcount'))
        ->toBe('alter table `custom_field_values` drop index `idx_cfg_headcount`')
        ->and($builder->dropColumnStatement('cfg_headcount'))
        ->toBe('alter table `custom_field_values` drop column `cfg_headcount`');
});
