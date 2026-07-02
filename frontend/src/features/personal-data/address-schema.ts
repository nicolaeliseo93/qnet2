import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the address form, built as a factory for localized messages.
 * Only `line1` is required (mirrors the backend). The geo ids come from the
 * cascading selects (nullable until chosen) and `is_primary` marks the owner's
 * default address (ADR 0010).
 */
export function buildAddressSchema(t: TFunction) {
  return z.object({
    label: z.string().optional(),
    line1: z.string().min(1, t('personalData.addresses.line1Required')).max(255),
    line2: z.string().optional(),
    postal_code: z.string().max(20).optional(),
    country_id: z.number().nullable().optional(),
    state_id: z.number().nullable().optional(),
    province_id: z.number().nullable().optional(),
    city_id: z.number().nullable().optional(),
    is_primary: z.boolean(),
  })
}

export type AddressFormValues = z.infer<ReturnType<typeof buildAddressSchema>>
