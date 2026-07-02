<?php

namespace App\DataObjects\Attachments;

use Illuminate\Http\UploadedFile;

/**
 * Validated payload for uploading a file (POST /api/attachments).
 *
 * Declared DTO (no "magic flying array") so the StoreAttachmentRequest →
 * AttachmentService contract is explicit and the service reads typed properties
 * — see standards/architecture.md → Data Transfer Objects.
 *
 * `attachableType` is the resolved owning model class (FQCN), already mapped
 * from the public alias by the FormRequest; null when the file is uploaded
 * standalone.
 */
final readonly class CreateAttachmentData
{
    public function __construct(
        public UploadedFile $file,
        public ?string $collection = null,
        public ?string $attachableType = null,
        public ?int $attachableId = null,
    ) {}

    /**
     * Whether this upload should be linked to an owning model.
     */
    public function hasAttachable(): bool
    {
        return $this->attachableType !== null && $this->attachableId !== null;
    }
}
