/**
 * Products CRUD types. The generic table types live in `features/table/types.ts`;
 * this file holds only what is genuinely products-specific. Source of truth:
 * spec 0017 frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** Product classification (spec 0017). SERVICE-only for now; mirrors the `ProductType` enum. */
export type ProductType = 'SERVICE'

/** Minimal category projection hydrating the product's grid/detail. */
export interface ProductCategorySummary {
  id: number
  name: string
}

/** The product's effective business function, derived read-only from its category (spec 0023). */
export interface ProductBusinessFunctionSummary {
  id: number
  name: string
}

/**
 * Single product detail returned by GET/POST/PATCH /products (envelope
 * `data`). Matches `ProductResource`.
 */
export interface ProductDetail {
  id: number
  name: string
  description: string | null
  cost: number | null
  price: number | null
  category_id: number
  category: ProductCategorySummary | null
  product_type: ProductType
  created_at: string
  /**
   * Effective business function of the product's category, read-only
   * (spec 0023): null when the category has none, own or inherited.
   * Optional like `custom_fields` — the same lenient-projection convention
   * used elsewhere on this resource, to avoid touching every existing
   * fixture that predates this field.
   */
  business_function?: ProductBusinessFunctionSummary | null
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `ProductDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /products/{id}` (`show`). Used to
 * seed the edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface ProductDetailWithPermissions extends ProductDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /products (create). */
export interface CreateProductPayload {
  name: string
  description?: string | null
  cost: number
  price: number
  category_id: number
  product_type: ProductType
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/** Payload for PATCH /products/{id} (partial update). Generic fields are sparse (only what changed). */
export type UpdateProductPayload = Partial<CreateProductPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `ProductForm` component (mirrors `BusinessFunctionFormMode`).
 */
export type ProductFormMode = { type: 'create' } | { type: 'edit'; product: ProductDetailWithPermissions }
