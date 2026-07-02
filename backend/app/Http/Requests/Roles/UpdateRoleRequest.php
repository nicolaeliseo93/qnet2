<?php

namespace App\Http\Requests\Roles;

use App\DataObjects\Roles\UpdateRoleData;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/roles/{role}.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $role)). Rules mirror StoreRoleRequest but every field
 * is optional (`sometimes`) to support partial PATCH updates; the name unique
 * rule ignores the role being edited.
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the RolePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'permissions' => ['sometimes', 'array'],
            // Only real, code-defined permissions can be attached to a role.
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
            // Members assigned from the Role form. Each must be a real user id;
            // privilege-escalation filtering (super-admin) happens in the Service.
            'users' => ['sometimes', 'array'],
            'users.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateRoleData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateRoleData::fromValidated($validated);
    }
}
