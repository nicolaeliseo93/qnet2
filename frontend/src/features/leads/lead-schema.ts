import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the lead create/edit form, built as a factory so validation
 * messages are localized via the i18n `t` function. The shape mirrors the
 * frozen backend contract (spec 0024) 1:1: exactly 6 fields, no `code` (D-3).
 */

/** Backend `notes` column limit (`max:5000`). */
const NOTES_MAX_LENGTH = 5000

/**
 * BR-1: contact and campaign are unconditionally required (unlike campaigns'
 * conditionally-required classification fields), so a plain `refine` on the
 * controlled-select's `null` unset state is enough — no cross-field rule needed.
 */
function baseFields(t: TFunction) {
  return {
    referent_id: z
      .number()
      .nullable()
      .refine((value) => value !== null, { message: t('leads.form.referentRequired') }),
    campaign_id: z
      .number()
      .nullable()
      .refine((value) => value !== null, { message: t('leads.form.campaignRequired') }),
    operational_site_id: z.number().nullable(),
    source_id: z.number().nullable(),
    operator_id: z.number().nullable(),
    is_converted: z.boolean(),
    notes: z.string().max(NOTES_MAX_LENGTH, t('leads.form.notesMax')).nullable(),
  }
}

/** Create schema. */
export function buildCreateLeadSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateLeadSchema(t: TFunction) {
  return buildCreateLeadSchema(t)
}

export type CreateLeadFormValues = z.infer<ReturnType<typeof buildCreateLeadSchema>>
export type UpdateLeadFormValues = CreateLeadFormValues
