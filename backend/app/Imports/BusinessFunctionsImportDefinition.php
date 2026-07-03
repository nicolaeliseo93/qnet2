<?php

namespace App\Imports;

use App\DataObjects\BusinessFunctions\CreateBusinessFunctionData;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\BusinessFunctionService;

/**
 * Import definition for `business-functions` (spec 0012 AC-011/012).
 *
 * Columns: `name` (required, natural key) + `type` (optional,
 * business_unit|business_service|blank). The CSV template deliberately does
 * NOT carry a `description` column: BusinessFunction has no such column
 * (migration 2026_07_03_120000_create_business_functions_table declares only
 * name/is_business_unit/is_business_service/manager_id) and
 * CreateBusinessFunctionData has no `description` property either — `type` is
 * the DTO's only other create-time field, so the import exposes that instead
 * (see the Lane A handoff for this adjustment against the original spec text).
 *
 * Row creation delegates entirely to BusinessFunctionService::create() (no
 * duplicated logic): manager_id/users are never set by import (not columns
 * here), matching a plain CSV row with no picker UI.
 */
class BusinessFunctionsImportDefinition extends AbstractImportDefinition
{
    /** @var array<int, string> */
    private const array VALID_TYPES = ['business_unit', 'business_service'];

    public function __construct(private readonly BusinessFunctionService $service) {}

    public function domain(): string
    {
        return 'business-functions';
    }

    public function modelClass(): string
    {
        return BusinessFunction::class;
    }

    public function columns(): array
    {
        return [
            ['id' => 'name', 'required' => true],
            ['id' => 'type', 'required' => false],
        ];
    }

    public function validateRow(array $row, ImportRowContext $context): array
    {
        $errors = [];

        if (trim($row['name'] ?? '') === '') {
            $errors[] = 'name is required.';
        }

        $type = trim($row['type'] ?? '');

        if ($type !== '' && ! in_array($type, self::VALID_TYPES, true)) {
            $errors[] = 'type must be business_unit, business_service, or blank.';
        }

        return $errors;
    }

    public function dedupKey(array $row): ?string
    {
        $name = trim($row['name'] ?? '');

        return $name === '' ? null : mb_strtolower($name);
    }

    /**
     * Fetches only the `name` column and compares in PHP (no raw SQL), same
     * trade-off as GeoResolver — correct regardless of the underlying
     * database's collation (MySQL in production, SQLite in tests).
     */
    public function existsInDatabase(string $key): bool
    {
        return BusinessFunction::query()
            ->get(['name'])
            ->contains(static fn (BusinessFunction $businessFunction): bool => mb_strtolower($businessFunction->name) === $key);
    }

    public function createRow(User $actor, array $row): void
    {
        $type = trim($row['type'] ?? '');

        $this->service->create($actor, new CreateBusinessFunctionData(
            name: $row['name'],
            type: $type !== '' ? $type : null,
        ));
    }
}
