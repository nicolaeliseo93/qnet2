<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'permissions' => $this->getPermissionNames(),
            'users' => $this->memberIds(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Ids of the users currently assigned to this role (Spatie model_has_roles
     * pivot), so the frontend Role form can pre-select members in edit mode.
     *
     * Resolved with a single id-only pivot query against the relation on the
     * populated model. We deliberately do NOT eager-load via `$role->load('users')`:
     * Spatie's `Role::users()` derives the related model from the model instance's
     * `guard_name`, but Eloquent's eager-load builds the relation on a bare Role
     * (no `guard_name`), so it falls back to the request's default guard — `sanctum`
     * on an authenticated API call — for which no provider model is configured,
     * crashing with "Class name must be a valid object or a string". Calling
     * `$this->users()` on the loaded role (guard_name = `web`) resolves correctly
     * and, as RoleResource only ever projects a single role, this is one query — no
     * N+1.
     *
     * Plain member ids are returned for any role the caller is already authorized
     * to view (`roles.view`); no super-admin membership rule is leaked here — the
     * privilege guards (ADR 0011) gate membership writes, not this read projection.
     *
     * @return array<int, int>
     */
    private function memberIds(): array
    {
        $ids = $this->relationLoaded('users')
            ? $this->users->pluck('id')
            : $this->users()->pluck('users.id');

        return $ids->map(static fn ($id): int => (int) $id)->values()->all();
    }
}
