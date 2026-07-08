import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the ea-sector create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0018) 1:1; the anti-cycle
 * `parent_id` rule is enforced server-side (the picker also excludes the
 * sector's own subtree client-side as a UX affordance, not a validity gate).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('eaSectors.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('eaSectors.form.nameMax')),
    parent_id: z.number().nullable(),
  }
}

export function buildCreateEaSectorSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export function buildUpdateEaSectorSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export type CreateEaSectorFormValues = z.infer<ReturnType<typeof buildCreateEaSectorSchema>>
export type UpdateEaSectorFormValues = CreateEaSectorFormValues
