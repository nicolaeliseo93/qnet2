<?php

namespace App\Http\Resources;

use App\Models\ImportRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportRun
 *
 * The frozen `import_run` shape (spec 0012 data_contract, extended spec
 * 0033). `has_error_report` is derived (never the raw stored path — that
 * stays server-side, streamed only through the authorized GET .../errors
 * endpoint). `error_rows` is the contract name for the existing
 * `invalid_rows` column (spec 0033: no new column for it, see the wizard
 * columns migration) — every other spec-0033 counter/wizard field
 * (warning_rows/duplicate_rows/modified_rows/error_count/detected_columns/
 * column_mapping/global_config/dedup_strategy) is additive and null/0 for a
 * run that never entered the unified wizard flow (the 5 legacy domains).
 *
 * NOTE: JsonResource itself declares a protected `$resource` property (the
 * wrapped model) — since ImportRun ALSO has a column literally named
 * `resource`, `$this->resource` resolves to the WRAPPED MODEL, not the
 * column (no magic __get() kicks in for an already-declared property). A
 * local, explicitly-typed variable sidesteps the collision entirely.
 */
class ImportRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ImportRun $importRun */
        $importRun = $this->resource;

        return [
            'id' => $importRun->id,
            'resource' => $importRun->resource,
            'status' => $importRun->status->value,
            'original_filename' => $importRun->original_filename,
            'total_rows' => $importRun->total_rows,
            'valid_rows' => $importRun->valid_rows,
            'warning_rows' => $importRun->warning_rows,
            'error_rows' => $importRun->invalid_rows,
            'duplicate_rows' => $importRun->duplicate_rows,
            'imported_rows' => $importRun->imported_rows,
            'modified_rows' => $importRun->modified_rows,
            'error_count' => $importRun->error_count,
            'has_error_report' => $importRun->error_report_path !== null,
            'created_at' => $importRun->created_at,
            'detected_columns' => $importRun->detected_columns,
            'column_mapping' => $importRun->column_mapping,
            'global_config' => $importRun->global_config,
            'dedup_strategy' => $importRun->dedup_strategy,
        ];
    }
}
