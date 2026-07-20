<?php

namespace App\Models;

use App\Enums\ImportRowResolution;
use App\Enums\ImportRowStatus;
use App\Models\Abstracts\BaseModel;
use Database\Factories\ImportRunRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One staged file row of the unified import wizard (spec 0033), written by
 * StageImportJob (raw/mapped/extra values, recognizer/dedup output in
 * `resolved`, per-row `status`/`messages`) and later read back by
 * ProcessImportJob to commit — the process phase never re-parses the source
 * file. Also backs the SSRM review grid, where `is_edited` flags a row
 * corrected inline before confirm.
 */
class ImportRunRow extends BaseModel
{
    /** @use HasFactory<ImportRunRowFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'row_number',
        'raw_values',
        'mapped_values',
        'extra_values',
        'resolved',
        'status',
        'messages',
        'duplicate_of_id',
        'duplicate_meta',
        'resolution',
        'is_edited',
        'operator_id',
    ];

    protected $casts = [
        'import_run_id' => 'int',
        'row_number' => 'int',
        'raw_values' => 'array',
        'mapped_values' => 'array',
        'extra_values' => 'array',
        'resolved' => 'array',
        'status' => ImportRowStatus::class,
        'messages' => 'array',
        'duplicate_of_id' => 'int',
        'duplicate_meta' => 'array',
        'resolution' => ImportRowResolution::class,
        'is_edited' => 'bool',
        'operator_id' => 'int',
    ];

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    /**
     * Per-row Operator override (spec 0045): when set, overrides the run's
     * global `operator_id` for this staged row only.
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
