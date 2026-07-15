<?php

namespace App\Support\Import;

use App\Enums\ImportDedupMode;
use App\Http\Resources\ImportRunResource;
use App\Imports\ImportDefinition;
use App\Imports\Support\ColumnAnalysis;
use App\Imports\Support\ColumnMapper;
use App\Models\ImportRun;

/**
 * Builds the enriched `import_run` payload for GET /api/imports/{domain}/
 * {importRun} (spec 0033 data_contract): the frozen ImportRunResource shape
 * plus the resolved definition's wizard catalogue (fields/global_fields/
 * dedup_modes) and a LIVE-recomputed `suggested_mapping` — diffable against
 * whatever the actor has since edited into `column_mapping`, exactly like
 * AnalyzeImportJob's initial seed (App\Jobs\AnalyzeImportJob). For a legacy
 * (non-wizard) run these are simply the AbstractImportDefinition defaults
 * (empty/null), harmless additions to the frozen spec-0012 shape.
 *
 * `detected_columns` is re-exposed with its deterministic `key`
 * (ColumnAnalysis::columnKeys() — bare name on first occurrence,
 * "{name}#{index}" on a later duplicate) alongside name/index/duplicate:
 * `column_mapping`/`suggested_mapping` are BOTH keyed by this same `key`
 * (never the bare name), so two identically-named file columns never
 * collapse onto the same mapping entry.
 *
 * `review_fields` (spec 0033 delta D-2026-07-15-placeholder-review-fields)
 * is the definition's FINAL persisted field catalogue: the review grid
 * builds its editable columns from this, never from column_mapping's
 * targets, so an input-only field a recognizer replaces (e.g. leads'
 * `full_name` -> `first_name`/`last_name`) is never itself a grid column.
 */
final class ImportRunPayloadBuilder
{
    public function __construct(private readonly ColumnMapper $columnMapper) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ImportDefinition $definition, ImportRun $importRun): array
    {
        $payload = (new ImportRunResource($importRun))->resolve(request());

        $payload['detected_columns'] = $importRun->detected_columns !== null
            ? $this->withColumnKeys($importRun->detected_columns)
            : null;
        $payload['suggested_mapping'] = $importRun->detected_columns !== null
            ? $this->columnMapper->suggest($importRun->detected_columns, $definition->fields())->mapping
            : null;
        $payload['fields'] = $definition->fields();
        $payload['global_fields'] = $definition->globalConfig();
        $payload['review_fields'] = $definition->reviewFields();
        $payload['dedup_modes'] = array_map(
            static fn (ImportDedupMode $mode): string => $mode->value,
            $definition->dedupModes(),
        );

        return $payload;
    }

    /**
     * @param  array<int, array{name: string, index: int, duplicate: bool}>  $columns
     * @return array<int, array{key: string, name: string, index: int, duplicate: bool}>
     */
    private function withColumnKeys(array $columns): array
    {
        $keys = ColumnAnalysis::columnKeys($columns);

        return array_map(
            static fn (array $column, string $key): array => ['key' => $key, ...$column],
            $columns,
            $keys,
        );
    }
}
