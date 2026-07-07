<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\CompanySite;
use Illuminate\Http\UploadedFile;

/**
 * Manages a company site's single logo on top of the polymorphic attachment
 * system (spec 0018) — mirrors AvatarService exactly, one collection per site
 * instead of per user.
 */
class LogoService
{
    public function __construct(private readonly AttachmentService $attachments) {}

    /**
     * Set (replace) the site's logo and return the stored attachment.
     */
    public function set(CompanySite $companySite, UploadedFile $file): Attachment
    {
        $logo = $companySite->attach($file, CompanySite::LOGO_COLLECTION);

        $companySite->attachments()
            ->where('collection', CompanySite::LOGO_COLLECTION)
            ->whereKeyNot($logo->id)
            ->get()
            ->each(fn (Attachment $previous) => $this->attachments->delete($previous));

        return $logo;
    }

    /**
     * Remove the site's logo(s), if any (file + row).
     */
    public function remove(CompanySite $companySite): void
    {
        $companySite->attachments()
            ->where('collection', CompanySite::LOGO_COLLECTION)
            ->get()
            ->each(fn (Attachment $logo) => $this->attachments->delete($logo));
    }
}
