<?php

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\BusinessFunction;
use App\Models\User;
use RuntimeException;

/**
 * `user-business-functions` migration source (spec 0013, extended): the
 * user-side counterpart of BusinessFunctionsSource's AC-010 attach. Where that
 * source populates the `business_function_user` pivot from each function's
 * `user_ids`, this one reads a dedicated junction endpoint of
 * `{user_id, business_function_id}` pairs — both EXTERNAL ids — and attaches
 * the association onto the pivot after remapping each side via `old_id`.
 *
 * The pivot is written directly on the BelongsToMany relation (never the
 * Service's `sync`, which full-replaces and would detach the function's other
 * members): attaching one association is a pure relational remap, exactly the
 * pivot-population concern spec 0013 assigns to the source itself. The unique
 * `(business_function_id, user_id)` constraint makes a re-attach a no-op, so
 * re-import is idempotent (an already-attached pair is skipped, never
 * duplicated). An unresolved side is a non-fatal warning: nothing is attached
 * this run, but re-running once the parent exists back-fills it.
 */
class UserBusinessFunctionsSource extends AbstractMigrationSource
{
    public function key(): string
    {
        return 'user-business-functions';
    }

    public function label(): string
    {
        return 'User business functions';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    public function columns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'user_id', 'label' => 'User (external id)', 'type' => 'number'],
            ['id' => 'business_function_id', 'label' => 'Business function (external id)', 'type' => 'number'],
        ];
    }

    public function endpoint(): string
    {
        return 'user-business-functions';
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'user_id' => $record['user_id'] ?? null,
            'business_function_id' => $record['business_function_id'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalUserId = $record['user_id'] ?? null;
        $externalFunctionId = $record['business_function_id'] ?? null;

        if ($externalUserId === null || $externalUserId === '' || $externalFunctionId === null || $externalFunctionId === '') {
            throw new RuntimeException('user_id and business_function_id are required.');
        }

        $userId = $this->resolveOldId(User::class, $externalUserId);
        $functionId = $this->resolveOldId(BusinessFunction::class, $externalFunctionId);

        if ($userId === null || $functionId === null) {
            return MigrationRowOutcome::skipped(
                $this->unresolvedWarnings($externalUserId, $userId, $externalFunctionId, $functionId),
            );
        }

        /** @var BusinessFunction $businessFunction */
        $businessFunction = BusinessFunction::query()->findOrFail($functionId);

        // The unique pivot constraint already guarantees no duplicate row, but
        // the existence check keeps the skipped/created counters honest.
        if ($businessFunction->users()->whereKey($userId)->exists()) {
            return MigrationRowOutcome::skipped();
        }

        $businessFunction->users()->attach($userId);

        return MigrationRowOutcome::created();
    }

    /**
     * The unresolved-side warnings for a junction row that could not be
     * attached because one (or both) external references have not been
     * migrated yet.
     *
     * @return array<int, string>
     */
    private function unresolvedWarnings(int|string $externalUserId, ?int $userId, int|string $externalFunctionId, ?int $functionId): array
    {
        $warnings = [];

        if ($userId === null) {
            $warnings[] = "Unresolved user reference (external id {$externalUserId}).";
        }

        if ($functionId === null) {
            $warnings[] = "Unresolved business function reference (external id {$externalFunctionId}).";
        }

        return $warnings;
    }
}
