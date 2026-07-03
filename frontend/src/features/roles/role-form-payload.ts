import { sameFieldPermissions } from '@/features/roles/field-permission-toggle'
import type {
  CreateRolePayload,
  RoleDetailWithPermissions,
  UpdateRolePayload,
} from '@/features/roles/types'
import type { RoleFormValues } from '@/features/roles/use-role-form'

/** Builds the create payload. */
export function buildCreatePayload(values: RoleFormValues): CreateRolePayload {
  return {
    name: values.name,
    permissions: values.permissions,
    users: values.users,
    field_permissions: values.field_permissions,
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original role.
 */
export function buildUpdatePayload(
  values: RoleFormValues,
  original: RoleDetailWithPermissions,
): UpdateRolePayload {
  const payload: UpdateRolePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (!samePermissions(values.permissions, original.permissions)) {
    payload.permissions = values.permissions
  }
  // Only send `users` when it changed from the original membership
  // (`RoleResource.users`); an unchanged selection is omitted from the PATCH.
  if (!sameIdSet(values.users, original.users ?? [])) {
    payload.users = values.users
  }
  // Same omit-when-unchanged convention for the field-permission matrix
  // (spec 0006) — an untouched matrix round-trips as "leave untouched".
  if (!sameFieldPermissions(values.field_permissions, original.field_permissions)) {
    payload.field_permissions = values.field_permissions
  }

  return payload
}

/** Order-insensitive comparison of two permission lists. */
function samePermissions(a: string[], b: string[]): boolean {
  return sameSet(a, b)
}

/** Order-insensitive comparison of two id lists. */
function sameIdSet(a: number[], b: number[]): boolean {
  return sameSet(a, b)
}

/** Order-insensitive equality of two primitive lists. */
function sameSet<T>(a: T[], b: T[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const set = new Set(b)
  return a.every((value) => set.has(value))
}
