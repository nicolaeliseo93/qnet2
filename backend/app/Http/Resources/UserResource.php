<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            // Roles as {id, name} objects: the edit form drives the for-select
            // picker by id (ADR 0011) while the detail view still renders names —
            // both without a second lookup. `name` stays the membership identity.
            'roles' => $this->roles
                ->map(static fn ($role): array => [
                    'id' => (int) $role->id,
                    'name' => $role->name,
                ])
                ->values(),
            // Avatar embedded inline as a data: URI so the client renders it
            // directly, with no extra authenticated request (null when unset).
            'avatar_url' => $this->avatarDataUri(),
            // The nested personal-data tree, emitted only when the card is loaded
            // AND present (the nested user write returns it; an account-only user
            // or a plain read omits the key entirely) — ADR 0012.
            'personal_data' => $this->when(
                $this->relationLoaded('personalData') && $this->personalData !== null,
                fn () => new PersonalDataResource($this->personalData),
            ),
            // The nested employment tree (spec 0015), same discipline as
            // personal_data: emitted only when loaded AND present.
            'employment' => $this->when(
                $this->relationLoaded('employment') && $this->employment !== null,
                fn () => new EmploymentResource($this->employment),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
