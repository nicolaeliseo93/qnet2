<?php

namespace App\Http\Resources;

use App\Models\ImportRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportRun
 *
 * The frozen `import_run` shape (spec 0012 data_contract). `has_error_report`
 * is derived (never the raw stored path — that stays server-side, streamed
 * only through the authorized GET .../errors endpoint).
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
            'invalid_rows' => $importRun->invalid_rows,
            'imported_rows' => $importRun->imported_rows,
            'has_error_report' => $importRun->error_report_path !== null,
            'created_at' => $importRun->created_at,
        ];
    }
}
