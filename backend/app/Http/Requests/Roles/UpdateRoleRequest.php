<?php

namespace App\Http\Requests\Roles;

use App\DataObjects\Roles\UpdateRoleData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesFieldPermissionsMatrix;
use App\Models\Role;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/roles/{role}.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $role)). Rules mirror StoreRoleRequest but every field
 * is optional (`sometimes`) to support partial PATCH updates; the name unique
 * rule ignores the role being edited.
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted field
 * the actor cannot edit on this specific $role (e.g. `name`/`permissions` on
 * the protected super-admin role).
 * ValidatesFieldPermissionsMatrix (spec 0006) validates the optional
 * `field_permissions` matrix (resource registered, field in that resource's
 * catalogue, flags boolean).
 */
class UpdateRoleRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesFieldPermissionsMatrix;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the RolePolicy.
        return true;
    }

    /**
     * Apply the field-level authorization gate (spec 0004) and the
     * field-permission matrix validation (spec 0006).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateFieldPermissionsMatrix($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'roles';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Role $role */
        $role = $this->route('role');

        return $role;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return array_merge([
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
        ], $this->fieldPermissionsRules());
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
