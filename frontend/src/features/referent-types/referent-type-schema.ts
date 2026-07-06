import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the referent-type create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `businessFunctions`). The shape mirrors the frozen backend contract
 * (spec 0016) 1:1.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('referentTypes.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('referentTypes.form.nameMax')),
  }
}

/** Create schema. */
export function buildCreateReferentTypeSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateReferentTypeSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export type CreateReferentTypeFormValues = z.infer<
  ReturnType<typeof buildCreateReferentTypeSchema>
>
export type UpdateReferentTypeFormValues = z.infer<
  ReturnType<typeof buildUpdateReferentTypeSchema>
>
