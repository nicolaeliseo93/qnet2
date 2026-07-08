import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend column limits (spec 0020 schema `company_site_banks`). */
const NAME_MAX_LENGTH = 191
const IBAN_MAX_LENGTH = 50
const NOTES_MAX_LENGTH = 191

/**
 * Generic IBAN shape: two-letter country code, two check digits, then up to
 * 30 alphanumeric characters (ISO 13616). Mirrors the backend's format
 * validation rule (spec 0020: "iban?:string|null(max:50, formato IBAN)").
 * ASSUMPTION (flagged to the backend teammate): the exact regex is not frozen
 * in the spec beyond "formato IBAN" — this is the standard, country-agnostic
 * shape; tighten together if the backend adopts a stricter one.
 */
const IBAN_PATTERN = /^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/i

/**
 * Zod schema for a single bank row (create/edit dialog inside
 * `banks-manager.tsx`), built as a factory for localized messages. Mirrors
 * the backend nested rules (`banks.*.name` required, `banks.*.iban` nullable
 * + IBAN format, `banks.*.notes` nullable).
 */
export function buildBankSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .min(1, t('companySites.form.banks.nameRequired'))
      .max(NAME_MAX_LENGTH, t('companySites.form.banks.nameMax')),
    iban: z
      .string()
      .max(IBAN_MAX_LENGTH, t('companySites.form.banks.ibanMax'))
      .refine((value) => value === '' || IBAN_PATTERN.test(value), {
        message: t('companySites.form.banks.ibanInvalid'),
      }),
    notes: z.string().max(NOTES_MAX_LENGTH, t('companySites.form.banks.notesMax')),
    // The site's preferred bank; the manager enforces at most one across the
    // list (single-primary, mirroring contacts/addresses).
    is_primary: z.boolean(),
  })
}

export type BankFormValues = z.infer<ReturnType<typeof buildBankSchema>>
