<?php

namespace Database\Seeders;

use App\Models\CompanySite;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Seed demo file attachments through the SAME write path the app uses
 * (`HasAttachments::attach()` → AttachmentService), so the polymorphic
 * `attachments` graph is exercised end-to-end rather than raw-inserted:
 *
 *   - a User `avatar` for a subset of users (User::avatar / avatarDataUri)
 *   - a CompanySite `logo` for a subset of sites (CompanySite::logo)
 *
 * Each image is a tiny solid-colour PNG generated on the fly (GD), colour
 * derived from the owner id so it is stable across runs. Idempotent: an owner
 * that already carries an attachment in the target collection is skipped, so
 * re-running never piles up duplicate avatars (users persist across runs;
 * company sites are recreated fresh by DemoCompanySiteSeeder and cascade their
 * old logos away on delete).
 *
 * Depends on DemoUsersSeeder and DemoCompanySiteSeeder (seeded earlier in
 * DemoDataSeeder) — a no-op when neither owner exists.
 */
class DemoAttachmentSeeder extends Seeder
{
    private const int AVATAR_EVERY_NTH_USER = 3;

    private const int LOGO_EVERY_NTH_SITE = 2;

    private const int IMAGE_SIZE = 96;

    public function run(): void
    {
        $this->seedAvatars();
        $this->seedLogos();
    }

    private function seedAvatars(): void
    {
        User::query()->orderBy('id')->get()
            ->filter(fn (User $user, int $index): bool => $index % self::AVATAR_EVERY_NTH_USER === 0)
            ->each(fn (User $user) => $this->attachImage($user, User::AVATAR_COLLECTION));
    }

    private function seedLogos(): void
    {
        CompanySite::query()->orderBy('id')->get()
            ->filter(fn (CompanySite $site, int $index): bool => $index % self::LOGO_EVERY_NTH_SITE === 0)
            ->each(fn (CompanySite $site) => $this->attachImage($site, CompanySite::LOGO_COLLECTION));
    }

    /**
     * Attach a generated PNG to the owner in the given collection, skipping
     * owners that already have one there (idempotency).
     */
    private function attachImage(Model $owner, string $collection): void
    {
        if ($owner->attachments()->where('collection', $collection)->exists()) {
            return;
        }

        $path = $this->generatePng((int) $owner->getKey());

        try {
            $owner->attach(
                new UploadedFile($path, $collection.'.png', 'image/png', null, true),
                $collection,
            );
        } finally {
            // AttachmentService copies the binary into the storage disk; drop
            // the source temp file whether or not the copy consumed it.
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Write a tiny solid-colour PNG to a temp file and return its path. The
     * colour is derived from the owner id so the same owner always renders the
     * same swatch across runs.
     */
    private function generatePng(int $ownerId): string
    {
        $image = imagecreatetruecolor(self::IMAGE_SIZE, self::IMAGE_SIZE);

        // Deterministic RGB from the id — spread across the byte range so
        // consecutive owners get visibly different swatches.
        $fill = imagecolorallocate(
            $image,
            ($ownerId * 53) % 256,
            ($ownerId * 97) % 256,
            ($ownerId * 151) % 256,
        );
        imagefill($image, 0, 0, $fill);

        $path = tempnam(sys_get_temp_dir(), 'demo-img').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }
}
