<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Manages a user's single avatar on top of the polymorphic attachment system.
 *
 * An avatar is just an Attachment in the `avatar` collection. A user keeps at
 * most one: setting a new avatar stores the new file first, then removes any
 * previous avatar (file + row), so there is never a window without an avatar and
 * a failed upload leaves the old one untouched.
 */
class AvatarService
{
    public function __construct(private readonly AttachmentService $attachments) {}

    /**
     * Set (replace) the user's avatar and return the stored attachment.
     */
    public function set(User $user, UploadedFile $file): Attachment
    {
        $avatar = $user->attach($file, User::AVATAR_COLLECTION);

        $user->attachments()
            ->where('collection', User::AVATAR_COLLECTION)
            ->whereKeyNot($avatar->id)
            ->get()
            ->each(fn (Attachment $previous) => $this->attachments->delete($previous));

        return $avatar;
    }

    /**
     * Remove the user's avatar(s), if any (file + row).
     */
    public function remove(User $user): void
    {
        $user->attachments()
            ->where('collection', User::AVATAR_COLLECTION)
            ->get()
            ->each(fn (Attachment $avatar) => $this->attachments->delete($avatar));
    }
}
