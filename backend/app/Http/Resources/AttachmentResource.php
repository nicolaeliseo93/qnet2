<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection' => $this->collection,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'uploaded_by' => $this->uploaded_by,
            // Authenticated, authorized download endpoint — the binary is never
            // served statically. The storage path/disk are intentionally hidden.
            'download_url' => url("/api/attachments/{$this->id}/download"),
            'created_at' => $this->created_at,
        ];
    }
}
