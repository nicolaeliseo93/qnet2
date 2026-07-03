import type { RoleFieldPermission } from '@/features/roles/types'

export type FieldPermissionFlag = 'visible' | 'editable' | 'required'

/**
 * Absence of a row for `(resource, field)` means "no restriction" (spec 0006
 * merge semantics): visible + editable, not required. Used both to render the
 * matrix's initial checkbox state and as the base row materialized on the
 * first toggle of a previously unrestricted field.
 */
const UNRESTRICTED: Omit<RoleFieldPermission, 'resource' | 'field'> = {
  visible: true,
  editable: true,
  required: false,
}

/** The current value of one flag for `(resource, field)`, defaulting to unrestricted. */
export function fieldPermissionFlag(
  rows: RoleFieldPermission[],
  resource: string,
  field: string,
  flag: FieldPermissionFlag,
): boolean {
  const row = rows.find((entry) => entry.resource === resource && entry.field === field)
  return row ? row[flag] : UNRESTRICTED[flag]
}

/**
 * Returns a new `field_permissions` array with one flag of one `(resource,
 * field)` row toggled — materializing the row (at the unrestricted defaults)
 * the first time a previously-unrestricted field is touched.
 */
export function toggleFieldPermission(
  rows: RoleFieldPermission[],
  resource: string,
  field: string,
  flag: FieldPermissionFlag,
  checked: boolean,
): RoleFieldPermission[] {
  const index = rows.findIndex((entry) => entry.resource === resource && entry.field === field)
  const base: RoleFieldPermission =
    index === -1 ? { resource, field, ...UNRESTRICTED } : rows[index]
  const next: RoleFieldPermission = { ...base, [flag]: checked }

  if (index === -1) {
    return [...rows, next]
  }
  const copy = [...rows]
  copy[index] = next
  return copy
}

/** Order-insensitive, value equality of two field-permission matrices. */
export function sameFieldPermissions(
  a: RoleFieldPermission[],
  b: RoleFieldPermission[],
): boolean {
  if (a.length !== b.length) {
    return false
  }
  const key = (row: RoleFieldPermission) => `${row.resource}.${row.field}`
  const index = new Map(b.map((row) => [key(row), row]))
  return a.every((row) => {
    const match = index.get(key(row))
    return (
      !!match &&
      match.visible === row.visible &&
      match.editable === row.editable &&
      match.required === row.required
    )
  })
}
