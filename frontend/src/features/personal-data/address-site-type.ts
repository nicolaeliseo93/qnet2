import type { SiteType } from '@/features/personal-data/types'

/**
 * i18n key per site type, shared by the dialog `AddressForm` and the
 * quick-create `AddressCreateField` (spec 0020) so both surfaces show
 * identical wording without duplicating the map.
 */
export const SITE_TYPE_LABEL_KEYS: Record<SiteType, string> = {
  legal_seat: 'personalData.addresses.siteTypeLegalSeat',
  delivery: 'personalData.addresses.siteTypeDelivery',
  billing: 'personalData.addresses.siteTypeBilling',
  operational_site: 'personalData.addresses.siteTypeOperationalSite',
}

/** DB default (`SiteTypeEnum::Billing`): preselected for a brand new address. */
export const DEFAULT_SITE_TYPE: SiteType = 'billing'
