<?php

declare(strict_types=1);

namespace App\Authorization;

use Spatie\Permission\Models\Permission;

/**
 * The subset of the permission catalogue that is directly assignable from the
 * Role form: permissions whose resource prefix is a registered "form-module"
 * resource (config/authorization.php — users, roles, business-functions,
 * companies, operational-sites).
 *
 * Indirect sub-entity permissions (addresses.*, contacts.*, personal_data.*,
 * attachments.*) are governed via the field-permission matrix on their parent
 * form, so they are never offered here — and RoleService never drops the ones a
 * role already holds when it saves the form.
 *
 * Single source of truth shared by RolesTableDefinition (the offered catalogue,
 * for the form and the `permissions` set filter) and RoleService (preserving
 * unmanaged permissions on sync).
 */
final class AssignablePermissionCatalogue
{
    public function __construct(private readonly AuthorizationRegistry $registry) {}

    /**
     * Whether a permission name belongs to a form-module resource and may be
     * assigned/managed from the Role form.
     */
    public function isAssignable(string $permission): bool
    {
        return in_array($this->resourceOf($permission), $this->registry->resourceKeys(), true);
    }

    /**
     * The assignable permission names present in the catalogue, ordered by
     * name. Optionally narrowed by a case-insensitive substring and capped.
     *
     * @return array<int, string>
     */
    public function names(?string $search = null, ?int $limit = null): array
    {
        /** @var array<int, string> $all */
        $all = Permission::query()->orderBy('name')->pluck('name')->all();

        $matches = array_values(array_filter(
            $all,
            fn (string $name): bool => $this->isAssignable($name)
                && ($search === null || $search === '' || stripos($name, $search) !== false),
        ));

        return $limit === null ? $matches : array_slice($matches, 0, $limit);
    }

    /**
     * The resource prefix of a permission name (`users.view` → `users`; a
     * dotless name is its own prefix).
     */
    private function resourceOf(string $permission): string
    {
        $dot = strpos($permission, '.');

        return $dot === false ? $permission : substr($permission, 0, $dot);
    }
}
