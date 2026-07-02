<?php

namespace App\Http\Requests\Roles;

use App\DataObjects\Roles\CreateRoleData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/roles.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', Role::class)). The name is unique among roles and each
 * submitted permission must exist in the synced catalogue (permissions are
 * code-defined and created by `php artisan permissions:sync`, never free text).
 */
class StoreRoleRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
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
    public function toData(): CreateRoleData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateRoleData::fromValidated($validated);
    }
}
