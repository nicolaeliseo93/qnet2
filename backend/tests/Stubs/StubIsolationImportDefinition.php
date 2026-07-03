<?php

namespace Tests\Stubs;

use App\Imports\AbstractImportDefinition;
use App\Imports\ImportRowContext;
use App\Models\BusinessFunction;
use App\Models\User;
use RuntimeException;

/**
 * Second, minimal test-only ImportDefinition used ONLY to exercise
 * ProcessImportJob's per-row transaction isolation (a commit-time failure on
 * ONE row must never block the other valid rows): createRow() deliberately
 * throws for the sentinel name "Boom", so the test can assert the OTHER rows
 * still import and the run still completes.
 */
final class StubIsolationImportDefinition extends AbstractImportDefinition
{
    private const string SENTINEL_FAILING_NAME = 'Boom';

    public function domain(): string
    {
        return 'stub-widgets-isolation';
    }

    public function modelClass(): string
    {
        return BusinessFunction::class;
    }

    public function columns(): array
    {
        return [['id' => 'name', 'required' => true]];
    }

    public function validateRow(array $row, ImportRowContext $context): array
    {
        return trim($row['name'] ?? '') === '' ? ['name is required.'] : [];
    }

    public function dedupKey(array $row): ?string
    {
        $name = trim($row['name'] ?? '');

        return $name === '' ? null : mb_strtolower($name);
    }

    public function existsInDatabase(string $key): bool
    {
        return false;
    }

    public function createRow(User $actor, array $row): void
    {
        if ($row['name'] === self::SENTINEL_FAILING_NAME) {
            throw new RuntimeException('Simulated commit-time failure.');
        }

        BusinessFunction::create(['name' => $row['name']]);
    }
}
