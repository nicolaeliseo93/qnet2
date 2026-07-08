import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend column limit (spec 0020 `data_contract`). */
const NAME_MAX_LENGTH = 191

/**
 * Shared fields common to create and edit. The anagraphic (identity card +
 * contacts + single address) is NOT part of this schema: like the Registries
 * module, it is a buffered `PersonalDataDraft` owned by the form hook and
 * validated separately via `buildPersonalDataSchema`. Only the site's own
 * scalar `name` plus the Impostazioni-tab fields live here.
 */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('companySites.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('companySites.form.nameMax')),
    notes: z.string().optional(),
    // Settings tab: the owning company, responsibles (users for-select ids),
    // banking default and the two document progressives. The read-only
    // "Altro"/quotation_* fields are display-only and intentionally NOT part
    // of this schema (never submitted — see `company-site-form-payload.ts`).
    company_id: z.number().nullable(),
    responsible_rda_id: z.number().nullable(),
    responsible_tickets_id: z.number().nullable(),
    responsible_validation_contracts_id: z.number().nullable(),
    responsible_validation_contracts_two_id: z.number().nullable(),
    default_bank_id: z.number().nullable(),
    proforma_progressive: z.number().int().nullable(),
    invoice_progressive: z.number().int().nullable(),
  }
}

/** Create schema. */
export function buildCreateCompanySiteSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateCompanySiteSchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export type CreateCompanySiteFormValues = z.infer<
  ReturnType<typeof buildCreateCompanySiteSchema>
>
export type UpdateCompanySiteFormValues = z.infer<
  ReturnType<typeof buildUpdateCompanySiteSchema>
>
