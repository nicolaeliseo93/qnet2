/**
 * Groups flat permission names (e.g. `users.view`, `roles.create`) by their
 * resource prefix (the part before the first dot) so the role form can render
 * them as readable, collapsible sections instead of one long flat list.
 *
 * The permission catalogue is code-defined and arrives from the table config
 * (`permissions` set options) — the single source of truth, never hardcoded.
 */
export interface PermissionGroup {
  /** Resource prefix, e.g. "users". Permissions with no dot fall under "general". */
  resource: string
  /** Full permission names belonging to the resource, in input order. */
  permissions: string[]
}

const UNGROUPED = 'general'

/** The ability suffix of a permission name, e.g. `users.view` → `view`. */
export function permissionAbility(permission: string): string {
  const dot = permission.indexOf('.')
  return dot === -1 ? permission : permission.slice(dot + 1)
}

/**
 * Group permission names by resource prefix, preserving first-seen order for
 * both the groups and the permissions inside them.
 */
export function groupPermissions(permissions: string[]): PermissionGroup[] {
  const groups: PermissionGroup[] = []
  const index = new Map<string, PermissionGroup>()

  for (const permission of permissions) {
    const dot = permission.indexOf('.')
    const resource = dot === -1 ? UNGROUPED : permission.slice(0, dot)

    let group = index.get(resource)
    if (!group) {
      group = { resource, permissions: [] }
      index.set(resource, group)
      groups.push(group)
    }
    group.permissions.push(permission)
  }

  return groups
}
