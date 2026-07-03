<?php

namespace App\Services;

use App\DataObjects\Users\EmploymentData;
use App\Models\User;

/**
 * Single source of truth for persisting a user's nested employment profile
 * (spec 0015): a plain hasOne upsert/delete, with the two server-side
 * invariants enforced here (not trusted from the request):
 *
 *  - a manager cannot also report to someone (`is_manager` forces
 *    `reports_to_id` to null);
 *  - a user can never report to itself (defense in depth — the FormRequest
 *    already 422s this on update; a create can never self-reference since
 *    the user's own id does not exist yet at validation time).
 *
 * The caller (UserService::create/update) is responsible for the surrounding
 * transaction, mirroring ProfileWriter.
 */
class EmploymentWriter
{
    /**
     * Persist the nested employment profile for the user inside the caller's
     * transaction. No-op when `$employment` is null (the key was absent from
     * the request — leave the row untouched). Deletes the row when `$employment
     * ->delete` is true (an explicit `employment: null`); a delete on a user
     * with no row is itself a harmless no-op, so create and update share this
     * single code path.
     */
    public function write(User $user, ?EmploymentData $employment): void
    {
        if ($employment === null) {
            return;
        }

        if ($employment->delete) {
            $user->employment()->delete();

            return;
        }

        $user->employment()->updateOrCreate([], $this->guardedAttributes($user, $employment));
    }

    /**
     * The row attributes with the two server-side invariants applied.
     *
     * @return array<string, mixed>
     */
    private function guardedAttributes(User $user, EmploymentData $employment): array
    {
        $attributes = $employment->attributes();

        if ($employment->isManager || $attributes['reports_to_id'] === $user->id) {
            $attributes['reports_to_id'] = null;
        }

        return $attributes;
    }
}
