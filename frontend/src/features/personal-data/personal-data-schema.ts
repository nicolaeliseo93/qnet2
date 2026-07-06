import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the personal-data card form, built as a factory so validation
 * messages are localized via i18n. Mirrors the backend per-type contract:
 * an individual must carry first + last name, a company must carry a company
 * name; the birth date (if any) cannot be in the future.
 */
export function buildPersonalDataSchema(t: TFunction) {
  return z
    .object({
      type: z.enum(['individual', 'company']),
      first_name: z.string().optional(),
      last_name: z.string().optional(),
      company_name: z.string().optional(),
      tax_code: z.string().max(32).optional(),
      vat_number: z.string().max(32).optional(),
      // SDI recipient code (Codice Destinatario) — company e-invoicing only.
      sdi_code: z.string().max(32).optional(),
      // Empty string = "no date"; a value must be a real, non-future date.
      birth_date: z.string().optional(),
      // Individual only (default male); a company card carries no gender.
      gender: z.enum(['male', 'female']).optional(),
    })
    .superRefine((values, ctx) => {
      if (values.type === 'individual') {
        if (!values.first_name) {
          ctx.addIssue({
            code: 'custom',
            path: ['first_name'],
            message: t('personalData.form.firstNameRequired'),
          })
        }
        if (!values.last_name) {
          ctx.addIssue({
            code: 'custom',
            path: ['last_name'],
            message: t('personalData.form.lastNameRequired'),
          })
        }
      }

      if (values.type === 'company' && !values.company_name) {
        ctx.addIssue({
          code: 'custom',
          path: ['company_name'],
          message: t('personalData.form.companyNameRequired'),
        })
      }

      if (values.birth_date && new Date(values.birth_date) >= startOfToday()) {
        ctx.addIssue({
          code: 'custom',
          path: ['birth_date'],
          message: t('personalData.form.birthDateFuture'),
        })
      }
    })
}

/** Midnight today, so a `birth_date` equal to today is rejected (before:today). */
function startOfToday(): Date {
  const now = new Date()
  return new Date(now.getFullYear(), now.getMonth(), now.getDate())
}

export type PersonalDataFormValues = z.infer<
  ReturnType<typeof buildPersonalDataSchema>
>
