<?php

namespace App\Models\Concerns;

use App\Models\EmploymentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Drop-in employment profile for the User model (spec 0015).
 *
 *     class User extends Authenticatable
 *     {
 *         use HasEmployment;
 *     }
 *
 *     $user->employment;                // the profile (or null)
 *     $user->delete();                  // cascades: the row is removed too
 *
 * The DB foreign key already `cascadeOnDelete()`s on a real delete, so the
 * explicit cleanup here only guards a soft-delete-free hard delete path
 * consistently with the other Has* concerns (HasPersonalData/HasAttachments)
 * — belt and suspenders, never relied upon alone.
 */
trait HasEmployment
{
    public static function bootHasEmployment(): void
    {
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->employment()->delete();
        });
    }

    /**
     * The single employment profile owned by this model.
     */
    public function employment(): HasOne
    {
        return $this->hasOne(EmploymentProfile::class);
    }
}
