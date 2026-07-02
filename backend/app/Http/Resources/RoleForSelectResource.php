<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Role;
use Illuminate\Http\Request;

/**
 * For-select projection of a Role (GET /api/roles/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. Feeds the
 * user-form role multi-select, mirroring UserForSelectResource on the role side.
 *
 * @mixin Role
 */
class RoleForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
        ];
    }
}
