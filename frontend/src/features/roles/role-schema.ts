import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schemas for the role create/edit form, built as factories so validation
 * messages are localized via the i18n `t` function (same pattern as the users
 * form). The shapes mirror the backend contract 1:1.
 */

/**
 * A single field-permission matrix row (spec 0006). UX mirror only — the
 * backend is the source of truth for the merge; this just shapes the payload.
 */
const roleFieldPermissionSchema = z.object({
  resource: z.string(),
  field: z.string(),
  visible: z.boolean(),
  editable: z.boolean(),
  required: z.boolean(),
})

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('roles.form.nameRequired'))
      .max(255, t('roles.form.nameMax')),
    permissions: z.array(z.string()),
    users: z.array(z.number()),
    field_permissions: z.array(roleFieldPermissionSchema),
  }
}

/** Create schema. */
export function buildCreateRoleSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateRoleSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export type CreateRoleFormValues = z.infer<ReturnType<typeof buildCreateRoleSchema>>
export type UpdateRoleFormValues = z.infer<ReturnType<typeof buildUpdateRoleSchema>>
