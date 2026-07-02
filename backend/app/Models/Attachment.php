<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reusable, polymorphic file attachment.
 *
 * Stores only metadata; the binary lives on the `disk` at `path`. Attach to any
 * owning model with the HasAttachments trait (morphMany on `attachable`), e.g.:
 *
 *     class Invoice extends BaseModel
 *     {
 *         use HasAttachments;
 *     }
 *
 *     $invoice->attachments;
 *
 * @property string $disk
 * @property string $path
 * @property string $original_name
 */
class Attachment extends BaseModel
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, LogsModelActivity;

    protected $fillable = [
        'collection',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'collection' => 'string',
        'disk' => 'string',
        'path' => 'string',
        'original_name' => 'string',
        'mime_type' => 'string',
        'extension' => 'string',
        'size' => 'int',
        'uploaded_by' => 'int',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
