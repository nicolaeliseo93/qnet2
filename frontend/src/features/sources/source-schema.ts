import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the source create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `referentTypes`). The shape mirrors the frozen backend contract
 * (spec 0018) 1:1.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('sources.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('sources.form.nameMax')),
  }
}

/** Create schema. */
export function buildCreateSourceSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateSourceSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export type CreateSourceFormValues = z.infer<ReturnType<typeof buildCreateSourceSchema>>
export type UpdateSourceFormValues = z.infer<ReturnType<typeof buildUpdateSourceSchema>>
