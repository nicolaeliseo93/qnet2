import type { CompanySiteDetail } from '@/features/company-sites/types'

/**
 * "Altro" fields (spec 0020): always read-only regardless of role (backend
 * authorization ceiling `visibleReadonly`). `key` is both the authorization
 * metadata key and the `CompanySiteDetail` property to read the value from.
 * Kept in its own module (not `.tsx`) so `company-site-form-body.tsx` can read
 * `OTHER_FIELD_KEYS` for the tab's visibility gate without pulling in JSX.
 */
export const OTHER_FIELDS = [
  { key: 'accounting_manager_id', labelKey: 'accountingManagerId' },
  { key: 'store_id', labelKey: 'storeId' },
  { key: 'company_type', labelKey: 'companyType' },
  { key: 'commissions', labelKey: 'commissions' },
  { key: 'order_sites', labelKey: 'orderSites' },
  { key: 'payment_status_assign_technician', labelKey: 'paymentStatusAssignTechnician' },
  { key: 'payment_status_deposit', labelKey: 'paymentStatusDeposit' },
  { key: 'payment_status_balance', labelKey: 'paymentStatusBalance' },
  { key: 'default_payment_id', labelKey: 'defaultPaymentId' },
  { key: 'default_vat_id', labelKey: 'defaultVatId' },
  { key: 'other_category_id', labelKey: 'otherCategoryId' },
  { key: 'iso_category_id', labelKey: 'isoCategoryId' },
  { key: 'soa_category_id', labelKey: 'soaCategoryId' },
  { key: 'sic_category_id', labelKey: 'sicCategoryId' },
  { key: 'avv_category_id', labelKey: 'avvCategoryId' },
  { key: 'gdpr_category_id', labelKey: 'gdprCategoryId' },
  { key: 'res_category_id', labelKey: 'resCategoryId' },
  { key: 'pal_category_id', labelKey: 'palCategoryId' },
  { key: 'quattro_category_id', labelKey: 'quattroCategoryId' },
  { key: 'finage_category_id', labelKey: 'finageCategoryId' },
  { key: 'fondi_category_id', labelKey: 'fondiCategoryId' },
  { key: 'gare_category_id', labelKey: 'gareCategoryId' },
  { key: 'partnership_category_id', labelKey: 'partnershipCategoryId' },
  { key: 'progetti_category_id', labelKey: 'progettiCategoryId' },
  { key: 'status', labelKey: 'status' },
  { key: 'color', labelKey: 'color' },
  { key: 'surface_sqm', labelKey: 'surfaceSqm' },
] as const satisfies ReadonlyArray<{ key: keyof CompanySiteDetail; labelKey: string }>

/** The Altro field keys, exposed so the form body can gate the whole tab. */
export const OTHER_FIELD_KEYS = OTHER_FIELDS.map((field) => field.key)
