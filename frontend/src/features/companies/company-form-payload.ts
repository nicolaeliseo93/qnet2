import { isAddressPresent } from '@/features/companies/company-schema'
import type {
  CompanyDetailWithPermissions,
  CreateCompanyAddressPayload,
  CreateCompanyPayload,
  UpdateCompanyPayload,
} from '@/features/companies/types'
import type { CompanyFormValues } from '@/features/companies/use-company-form'

/** Builds the nested address payload once the block is known to carry a value. */
function toAddressPayload(
  address: CompanyFormValues['address'],
): CreateCompanyAddressPayload {
  return {
    line1: address.line1 ?? '',
    line2: address.line2 || null,
    postal_code: address.postal_code || null,
    country_id: address.country_id ?? null,
    state_id: address.state_id ?? null,
    province_id: address.province_id ?? null,
    city_id: address.city_id ?? null,
  }
}

/**
 * Builds the create payload. The address block is omitted entirely when the
 * user left every one of its fields blank — a company may have no address at
 * all (spec 0010 scope).
 */
export function buildCreatePayload(values: CompanyFormValues): CreateCompanyPayload {
  return {
    denomination: values.denomination,
    vat_number: values.vat_number || null,
    ...(isAddressPresent(values.address) ? { address: toAddressPayload(values.address) } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original company. The address, when present and changed, is always sent in
 * full (it fully rewrites the company's single address server-side);
 * clearing an existing address back to blank is out of scope (spec 0010) and
 * is treated as a no-op rather than sent as an (invalid, line1-less) update.
 */
export function buildUpdatePayload(
  values: CompanyFormValues,
  original: CompanyDetailWithPermissions,
): UpdateCompanyPayload {
  const payload: UpdateCompanyPayload = {}

  if (values.denomination !== original.denomination) {
    payload.denomination = values.denomination
  }

  const vatNumber = values.vat_number || null
  if (vatNumber !== original.vat_number) {
    payload.vat_number = vatNumber
  }

  if (isAddressPresent(values.address) && addressChanged(values.address, original.address)) {
    payload.address = toAddressPayload(values.address)
  }

  return payload
}

/**
 * Whether the form's address block differs from the original — compares only
 * the ids the payload actually carries (the resolved names are read-only
 * detail-view labels, not part of the request).
 */
function addressChanged(
  address: CompanyFormValues['address'],
  original: CompanyDetailWithPermissions['address'],
): boolean {
  if (!original) {
    return true
  }
  const next = toAddressPayload(address)
  return (
    next.line1 !== original.line1 ||
    next.line2 !== original.line2 ||
    next.postal_code !== original.postal_code ||
    next.country_id !== original.country_id ||
    next.state_id !== original.state_id ||
    next.province_id !== original.province_id ||
    next.city_id !== original.city_id
  )
}
