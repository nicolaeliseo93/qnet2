import { z } from 'zod'
import type { TFunction } from 'i18next'

/** Backend column limits (spec 0020 `data_contract`). */
const NAME_MAX_LENGTH = 191
const EMAIL_MAX_LENGTH = 191
const FISCAL_CODE_MAX_LENGTH = 20
const VAT_NUMBER_MAX_LENGTH = 20
const PHONE_MAX_LENGTH = 191
const LINE1_MAX_LENGTH = 255
const POSTAL_CODE_MAX_LENGTH = 20

/** The site's single embedded address block, before validation. */
interface AddressFields {
  line1?: string
  line2?: string
  postal_code?: string
  country_id?: number | null
  state_id?: number | null
  province_id?: number | null
  city_id?: number | null
}

/**
 * Whether at least one address field carries a value. Shared with
 * `company-site-form-payload` so the "line1 required only when the address
 * block is used" rule and the "omit an untouched address from the payload"
 * rule stay in sync (mirrors `features/companies/company-schema.ts`).
 */
export function isAddressPresent(address: AddressFields): boolean {
  return Boolean(
    address.line1 ||
      address.line2 ||
      address.postal_code ||
      address.country_id != null ||
      address.state_id != null ||
      address.province_id != null ||
      address.city_id != null,
  )
}

/** The site's single polymorphic address, cascading via `GeoSelect` (ADR 0010). */
function addressSchema(t: TFunction) {
  return z
    .object({
      line1: z.string().max(LINE1_MAX_LENGTH).optional(),
      line2: z.string().optional(),
      postal_code: z.string().max(POSTAL_CODE_MAX_LENGTH).optional(),
      country_id: z.number().nullable().optional(),
      state_id: z.number().nullable().optional(),
      province_id: z.number().nullable().optional(),
      city_id: z.number().nullable().optional(),
    })
    .superRefine((address, ctx) => {
      if (isAddressPresent(address) && !address.line1) {
        ctx.addIssue({
          code: 'custom',
          path: ['line1'],
          message: t('companySites.form.line1Required'),
        })
      }
    })
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('companySites.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('companySites.form.nameMax')),
    email: z
      .string()
      .min(1, t('companySites.form.emailRequired'))
      .max(EMAIL_MAX_LENGTH)
      .email(t('companySites.form.emailInvalid')),
    fiscal_code: z.string().max(FISCAL_CODE_MAX_LENGTH).optional(),
    vat_number: z.string().max(VAT_NUMBER_MAX_LENGTH).optional(),
    phone: z.string().max(PHONE_MAX_LENGTH).optional(),
    pec: z.string().max(PHONE_MAX_LENGTH).optional(),
    fax: z.string().max(PHONE_MAX_LENGTH).optional(),
    notes: z.string().optional(),
    address: addressSchema(t),
    // Settings tab: responsibles (users for-select ids), banking default and
    // the two document progressives. The read-only "Altro"/quotation_* fields
    // are display-only and intentionally NOT part of this schema (never
    // submitted — see `company-site-form-payload.ts`).
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
