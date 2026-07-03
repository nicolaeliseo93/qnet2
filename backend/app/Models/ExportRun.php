<?php

namespace App\Models;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\Abstracts\BaseModel;
use Database\Factories\ExportRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per generic table export request (spec 0014): tracks the async
 * (queued) generation flow's status and the downloadable generated file.
 * `resource` is the `{domain}` key (App\Tables\TableRegistry /
 * config/tables.php) the run was started against — NOT a foreign key,
 * mirroring ImportRun.
 */
class ExportRun extends BaseModel
{
    /** @use HasFactory<ExportRunFactory> */
    use HasFactory;

    protected $fillable = [
        'resource',
        'user_id',
        'status',
        'format',
        'original_filename',
        'state',
        'file_path',
        'row_count',
    ];

    protected $casts = [
        'resource' => 'string',
        'status' => ExportStatus::class,
        'format' => ExportFormat::class,
        'original_filename' => 'string',
        'state' => 'array',
        'file_path' => 'string',
        'row_count' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
