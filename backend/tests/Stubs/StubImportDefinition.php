<?php

namespace Tests\Stubs;

use App\DataObjects\BusinessFunctions\CreateBusinessFunctionData;
use App\Imports\AbstractImportDefinition;
use App\Imports\ImportRowContext;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\BusinessFunctionService;

/**
 * Test-only ImportDefinition exercising the GENERIC import engine (registry,
 * controller, jobs, authz) end-to-end without depending on any of the 3 real
 * per-module definitions a later gate registers.
 *
 * It intentionally reuses BusinessFunction's already-migrated model + Policy
 * (`business-functions.import`, via BasePolicy) purely so authorizeImport()
 * has a real Gate/Policy to resolve against; domain()/resource() return a
 * DIFFERENT string ("stub-widgets") so this stub never collides with the real
 * `business-functions` registration a follow-up gate adds to
 * config/imports.php. Row creation delegates to the real
 * BusinessFunctionService (the exact reuse pattern a concrete definition
 * follows), proving the createRow() contract end-to-end.
 *
 * Columns: `name` (required, natural key, case-insensitive), `type` (optional,
 * passed straight through to CreateBusinessFunctionData::type).
 */
final class StubImportDefinition extends AbstractImportDefinition
{
    public function __construct(private readonly BusinessFunctionService $service) {}

    public function domain(): string
    {
        return 'stub-widgets';
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

        if ($type !== '' && ! in_array($type, ['business_unit', 'business_service'], true)) {
            $errors[] = 'type must be business_unit, business_service, or blank.';
        }

        return $errors;
    }

    public function dedupKey(array $row): ?string
    {
        $name = trim($row['name'] ?? '');

        return $name === '' ? null : mb_strtolower($name);
    }

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
