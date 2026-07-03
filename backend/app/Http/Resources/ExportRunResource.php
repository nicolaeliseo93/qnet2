<?php

namespace App\Http\Resources;

use App\Enums\ExportStatus;
use App\Models\ExportRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExportRun
 *
 * The frozen `export_run` shape (spec 0014). `has_file` is derived (never the
 * raw stored path — that stays server-side, streamed only through the
 * authorized GET .../download endpoint).
 *
 * NOTE: JsonResource itself declares a protected `$resource` property (the
 * wrapped model) — since ExportRun ALSO has a column literally named
 * `resource`, `$this->resource` resolves to the WRAPPED MODEL, not the
 * column. A local, explicitly-typed variable sidesteps the collision
 * entirely, mirroring ImportRunResource.
 */
class ExportRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ExportRun $exportRun */
        $exportRun = $this->resource;

        return [
            'id' => $exportRun->id,
            'resource' => $exportRun->resource,
            'status' => $exportRun->status->value,
            'format' => $exportRun->format->value,
            'original_filename' => $exportRun->original_filename,
            'row_count' => $exportRun->row_count,
            'has_file' => $exportRun->file_path !== null && $exportRun->status === ExportStatus::Completed,
            'created_at' => $exportRun->created_at,
        ];
    }
}
