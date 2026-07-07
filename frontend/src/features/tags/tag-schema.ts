import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the tag create/edit form, built as a factory so validation
 * messages are localized via the i18n `t` function (same pattern as
 * `referentTypes`/`sources`). The shape mirrors the frozen backend contract
 * (spec 0019) 1:1.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('tags.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('tags.form.nameMax')),
  }
}

/** Create schema. */
export function buildCreateTagSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateTagSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export type CreateTagFormValues = z.infer<ReturnType<typeof buildCreateTagSchema>>
export type UpdateTagFormValues = z.infer<ReturnType<typeof buildUpdateTagSchema>>
