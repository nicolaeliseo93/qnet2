<?php

namespace Tests\Unit\Jobs\Fixtures;

use App\Enums\ImportDedupMode;
use App\Imports\AbstractImportDefinition;
use App\Imports\ImportRowContext;
use App\Models\BusinessFunction;
use App\Models\ImportRunRow;
use App\Models\User;
use RuntimeException;

/**
 * Test-only wizard ImportDefinition exercising AnalyzeImportJob/StageImportJob/
 * ProcessStagedImportJob generically (B3 lane), without depending on
 * LeadsImportDefinition (a parallel lane). Fields: `full_name` (required),
 * `email` (required, format-checked). Reuses BusinessFunction purely as the
 * "domain record" persistRow() creates (one BusinessFunction per row, named
 * after `full_name`) and as authorizeImport()'s Policy target — the SAME
 * reuse pattern StubImportDefinition already applies for the legacy engine.
 *
 * Duplicate simulation: resolveDuplicate() returns a fixed id for the
 * sentinel email `duplicate@test.com`, so tests can exercise every dedup
 * strategy without a real domain match. Commit-time-failure simulation:
 * persistRow() throws for the sentinel full_name `Boom`, mirroring
 * StubIsolationImportDefinition's per-row isolation test.
 */
final class FakeWizardImportDefinition extends AbstractImportDefinition
{
    public const int DUPLICATE_ID = 999;

    public const string DUPLICATE_EMAIL = 'duplicate@test.com';

    public const string SENTINEL_FAILING_NAME = 'Boom';

    public function domain(): string
    {
        return 'wizard-widgets';
    }

    public function modelClass(): string
    {
        return BusinessFunction::class;
    }

    public function columns(): array
    {
        return [
            ['id' => 'full_name', 'required' => true],
            ['id' => 'email', 'required' => true],
        ];
    }

    public function fields(): array
    {
        return [
            ['id' => 'full_name', 'label' => 'Full name', 'required' => true, 'group' => null, 'type' => 'text'],
            ['id' => 'email', 'label' => 'Email', 'required' => true, 'group' => null, 'type' => 'text'],
        ];
    }

    public function recognizers(): array
    {
        return [FakeWizardRecognizer::class];
    }

    public function supportsExtraFields(): bool
    {
        return true;
    }

    public function dedupModes(): array
    {
        return [ImportDedupMode::CreateNew, ImportDedupMode::UpdateExisting, ImportDedupMode::Ignore, ImportDedupMode::Manual];
    }

    public function validateRow(array $row, ImportRowContext $context): array
    {
        $errors = [];

        if (trim((string) ($row['full_name'] ?? '')) === '') {
            $errors[] = 'full_name is required.';
        }

        $email = trim((string) ($row['email'] ?? ''));

        if ($email === '') {
            $errors[] = 'email is required.';
        } elseif (! str_contains($email, '@')) {
            $errors[] = 'email must be a valid address.';
        }

        return $errors;
    }

    public function dedupKey(array $row): ?string
    {
        return null;
    }

    public function existsInDatabase(string $key): bool
    {
        return false;
    }

    public function createRow(User $actor, array $row): void
    {
        BusinessFunction::create(['name' => $row['full_name']]);
    }

    public function persistRow(User $actor, ImportRunRow $row, array $globalConfig, string $dedupStrategy): void
    {
        $name = (string) ($row->mapped_values['full_name'] ?? '');

        if ($name === self::SENTINEL_FAILING_NAME) {
            throw new RuntimeException('Simulated commit-time failure.');
        }

        BusinessFunction::create(['name' => $name]);
    }

    public function resolveDuplicate(array $mapped): ?int
    {
        return ($mapped['email'] ?? null) === self::DUPLICATE_EMAIL ? self::DUPLICATE_ID : null;
    }
}
