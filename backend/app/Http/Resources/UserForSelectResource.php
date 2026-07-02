<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * For-select projection of a User (GET /api/users/for-select).
 *
 * Minimal by design (ADR 0011): label = name, subtitle = email, optional
 * avatar_url as an inlined data URI when the user has an avatar. Relies on the
 * service projecting only the fields needed by the select plus the eager-loaded
 * avatar relation — never a full UserResource.
 *
 * @mixin User
 */
class UserForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->email,
            'avatar_url' => $this->avatarDataUri(),
        ];
    }
}
