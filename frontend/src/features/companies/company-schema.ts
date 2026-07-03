import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schemas for the company create/edit form, built as factories so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `users`/`roles`/`businessFunctions`). The shapes mirror the frozen
 * backend contract (spec 0010) 1:1.
 */

/** Backend column limits (spec 0010 data_contract). */
const DENOMINATION_MAX_LENGTH = 255
const VAT_NUMBER_MAX_LENGTH = 50
const LINE1_MAX_LENGTH = 255
const POSTAL_CODE_MAX_LENGTH = 20

/** The company's single embedded address block, before validation. */
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
 * `company-form-payload` so the "line1 required only when the address block
 * is used" rule and the "omit an untouched address from the payload" rule
 * stay in sync (spec 0010: a company may have no address at all).
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

/**
 * The company's single polymorphic address (ADR 0010). Every field is
 * optional — a company may have none — but once any of them carries a value,
 * `line1` becomes required, mirroring the backend's "line1 required se
 * address presente" rule. The geo ids come from the cascading `GeoSelect`
 * (nullable until chosen).
 */
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
          message: t('companies.form.line1Required'),
        })
      }
    })
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    denomination: z
      .string()
      .min(1, t('companies.form.denominationRequired'))
      .max(DENOMINATION_MAX_LENGTH, t('companies.form.denominationMax')),
    vat_number: z.string().max(VAT_NUMBER_MAX_LENGTH).optional(),
    address: addressSchema(t),
  }
}

/** Create schema. */
export function buildCreateCompanySchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateCompanySchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export type CreateCompanyFormValues = z.infer<ReturnType<typeof buildCreateCompanySchema>>
export type UpdateCompanyFormValues = z.infer<ReturnType<typeof buildUpdateCompanySchema>>
