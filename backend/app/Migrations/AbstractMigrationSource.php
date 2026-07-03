<?php

namespace App\Migrations;

use App\Migrations\Support\ExternalApiClient;
use App\Models\MigrationRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Shared machinery for every concrete MigrationSource (spec 0013). Mirrors
 * App\Imports\AbstractImportDefinition: a concrete source declares only its
 * key/label/columns, its external endpoint, how to map one record to a
 * preview row, how to read its `id`, and how to import one row
 * (processRow()) — the cross-cutting parts (paginating the external system,
 * the read-only preview shape, per-row transaction isolation, run counters
 * and report) live here once.
 *
 * Assumes the external system returns a Laravel-Resource-shaped page
 * (`{data:[...], meta:{current_page,per_page,total}}`) — see spec 0013
 * context. `fetchPage()` is the single seam a source can override for a
 * different external shape without touching the rest of the engine.
 */
abstract class AbstractMigrationSource implements MigrationSource
{
    public function __construct(protected readonly ExternalApiClient $client) {}

    /**
     * Relative path (under config('migrations.base_url')) of this resource's
     * list endpoint on the external system.
     */
    abstract protected function endpoint(): string;

    /**
     * Map one external record to a preview row (keys = column id).
     *
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    abstract protected function mapRow(array $record): array;

    /**
     * The external record's own id (the value `old_id` is set to), or null
     * when the record carries none — a fatal, per-row error (never silently
     * skipped) surfaced via processRow().
     *
     * @param  array<string, mixed>  $record
     */
    abstract protected function externalId(array $record): int|string|null;

    /**
     * Import ONE external record: skip if its `old_id` already exists,
     * otherwise create it via the domain Service, set `old_id`, and apply any
     * relational remap — all inside the caller's per-row transaction
     * (importRow()). Thrown exceptions isolate to this row.
     *
     * @param  array<string, mixed>  $record
     */
    abstract protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome;

    public function preview(MigrationQuery $query): MigrationPage
    {
        $payload = $this->fetchPage($query->page, $query->perPage);
        $records = $this->extractRecords($payload);
        $meta = $this->extractMeta($payload);

        $rows = array_map(fn (array $record): array => $this->mapRow($record), $records);
        $total = $this->extractTotal($meta);

        return new MigrationPage(
            rows: $rows,
            page: $query->page,
            perPage: $query->perPage,
            total: $total,
            hasMore: $this->hasMorePages($total, $query->page, $query->perPage, count($records)),
        );
    }

    public function import(MigrationImportContext $context): void
    {
        $page = 1;
        $perPage = (int) config('migrations.import_batch_size', 100);

        do {
            $payload = $this->fetchPage($page, $perPage);
            $records = $this->extractRecords($payload);

            foreach ($records as $record) {
                $this->importRow($context, $record);
            }

            $total = $this->extractTotal($this->extractMeta($payload));
            $hasMore = $this->hasMorePages($total, $page, $perPage, count($records));
            $page++;
        } while ($hasMore);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchPage(int $page, int $perPage): array
    {
        return $this->client->get($this->endpoint(), [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function extractRecords(array $payload): array
    {
        return (array) ($payload['data'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function extractMeta(array $payload): array
    {
        return (array) ($payload['meta'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function extractTotal(array $meta): ?int
    {
        return array_key_exists('total', $meta) ? (int) $meta['total'] : null;
    }

    /**
     * A known total resolves has_more precisely; an absent total (AC-007)
     * falls back to "the page came back full", a reasonable heuristic for an
     * unknown-length external listing.
     */
    protected function hasMorePages(?int $total, int $page, int $perPage, int $countReceived): bool
    {
        if ($total !== null) {
            return ($page * $perPage) < $total;
        }

        return $countReceived >= $perPage;
    }

    /**
     * Idempotence check (spec 0013): a row whose `old_id` already exists on
     * the target table is skipped, never duplicated/updated.
     *
     * @param  class-string<Model>  $targetClass
     */
    protected function existsByOldId(string $targetClass, int|string $externalId): bool
    {
        return $targetClass::query()->where('old_id', $externalId)->exists();
    }

    /**
     * Relational remap (spec 0013): resolve a parent referenced by its
     * EXTERNAL id to the qnet record's own id via `old_id`. Null when the
     * parent has not (yet) been migrated — the caller turns this into a
     * non-fatal warning.
     *
     * @param  class-string<Model>  $parentClass
     */
    protected function resolveOldId(string $parentClass, int|string $externalRef): ?int
    {
        /** @var int|null $id */
        $id = $parentClass::query()->where('old_id', $externalRef)->value('id');

        return $id;
    }

    /**
     * Import one external record in its own transaction, isolating a
     * commit-time failure to this row: increments the run's created/skipped/
     * failed counters, appends any warning/error to its report, never blocks
     * the remaining rows.
     *
     * @param  array<string, mixed>  $record
     */
    private function importRow(MigrationImportContext $context, array $record): void
    {
        $run = $context->run;
        $externalId = $this->externalId($record);

        try {
            $outcome = DB::transaction(fn (): MigrationRowOutcome => $this->processRow($context, $record));

            if ($outcome->skipped) {
                $run->increment('skipped_rows');
            } else {
                $run->increment('created_rows');

                foreach ($outcome->warnings as $warning) {
                    $this->appendReport($run, $externalId, 'warning', $warning);
                }
            }
        } catch (Throwable $exception) {
            $run->increment('failed_rows');
            $this->appendReport($run, $externalId, 'error', 'Failed to import the record: '.$exception->getMessage());
        }

        $run->increment('total_rows');
    }

    private function appendReport(MigrationRun $run, int|string|null $externalId, string $level, string $message): void
    {
        $report = $run->report ?? [];
        $report[] = ['old_id' => $externalId, 'level' => $level, 'message' => $message];
        $run->update(['report' => $report]);
    }
}
