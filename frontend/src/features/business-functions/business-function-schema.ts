import { z } from 'zod'
import type { TFunction } from 'i18next'
import { BUSINESS_FUNCTION_TYPES } from '@/features/business-functions/types'

/**
 * Zod schemas for the business-function create/edit form, built as factories
 * so validation messages are localized via the i18n `t` function (same
 * pattern as `users`/`roles`). The shapes mirror the frozen backend contract
 * (spec 0010) 1:1.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

const businessFunctionTypeSchema = z.enum(BUSINESS_FUNCTION_TYPES)

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('businessFunctions.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('businessFunctions.form.nameMax')),
    // Mutually exclusive domain type; `null` maps to "none" (both boolean
    // columns cleared server-side).
    type: businessFunctionTypeSchema.nullable(),
    // Single-select responsabile (for-select standard): `null` = unset.
    manager_id: z.number().nullable(),
    // Multiselect associated users (for-select standard): ids, full-replace.
    users: z.array(z.number()),
  }
}

/** Create schema. */
export function buildCreateBusinessFunctionSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateBusinessFunctionSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export type CreateBusinessFunctionFormValues = z.infer<
  ReturnType<typeof buildCreateBusinessFunctionSchema>
>
export type UpdateBusinessFunctionFormValues = z.infer<
  ReturnType<typeof buildUpdateBusinessFunctionSchema>
>
