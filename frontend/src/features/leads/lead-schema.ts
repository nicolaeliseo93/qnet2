import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the lead create/edit form, built as a factory so validation
 * messages are localized via the i18n `t` function. The shape mirrors the
 * frozen backend contract (spec 0024) 1:1: exactly 6 fields, no `code` (D-3).
 */

/** Backend `notes` column limit (`max:5000`); exported for the form's character counter. */
export const NOTES_MAX_LENGTH = 5000

/**
 * A single free-form `extra_fields` row as edited in the field array (spec
 * 0033, AC-014). The key is required (trimmed): an empty key cannot map to
 * a meaningful `Record<string, string>` entry, so it is rejected rather
 * than silently dropped.
 */
function extraFieldEntrySchema(t: TFunction) {
  return z.object({
    key: z.string().trim().min(1, t('leads.form.extraFields.keyRequired')),
    value: z.string(),
  })
}

/**
 * BR-1/D-1: contact and campaign are unconditionally required, so a plain
 * `refine` on the controlled-select's `null` unset state is enough — no
 * cross-field rule needed. The explicit `: boolean` return type on each
 * predicate is load-bearing, not stylistic: without it, TS 5.5+'s automatic
 * type-predicate inference treats `value !== null` as a `value is number`
 * guard, silently narrowing the field's inferred type to non-nullable
 * `number` and breaking every consumer typed against the nullable form
 * value (`RelationSelectField`'s `Control<LeadFormValues>`, `useForm`'s
 * `defaultValues`). `lead_status_id` is nullable, NOT required (spec 0039
 * D-3): the server falls back to the system "Nuovo" status when omitted, and
 * the create form preselects it as soon as the for-select resolves.
 */
function baseFields(t: TFunction) {
  return {
    referent_id: z
      .number()
      .nullable()
      .refine((value): boolean => value !== null, { message: t('leads.form.referentRequired') }),
    campaign_id: z
      .number()
      .nullable()
      .refine((value): boolean => value !== null, { message: t('leads.form.campaignRequired') }),
    lead_status_id: z.number().nullable(),
    operational_site_id: z.number().nullable(),
    source_id: z.number().nullable(),
    operator_id: z.number().nullable(),
    notes: z.string().max(NOTES_MAX_LENGTH, t('leads.form.notesMax')).nullable(),
    extra_fields: z.array(extraFieldEntrySchema(t)),
  }
}

/**
 * Create schema. A top-level `superRefine` flags duplicate `extra_fields`
 * keys (case-insensitive): two rows resolving to the same `Record` key
 * would silently overwrite one another at the API boundary, so it is
 * caught here instead.
 */
export function buildCreateLeadSchema(t: TFunction) {
  return z.object(baseFields(t)).superRefine((values, ctx) => {
    const seenKeys = new Set<string>()
    values.extra_fields.forEach((entry, index) => {
      const normalizedKey = entry.key.trim().toLowerCase()
      if (!normalizedKey) return
      if (seenKeys.has(normalizedKey)) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: t('leads.form.extraFields.duplicateKey'),
          path: ['extra_fields', index, 'key'],
        })
        return
      }
      seenKeys.add(normalizedKey)
    })
  })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateLeadSchema(t: TFunction) {
  return buildCreateLeadSchema(t)
}

export type CreateLeadFormValues = z.infer<ReturnType<typeof buildCreateLeadSchema>>
export type UpdateLeadFormValues = CreateLeadFormValues
