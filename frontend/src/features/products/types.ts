/**
 * Products CRUD types. The generic table types live in `features/table/types.ts`;
 * this file holds only what is genuinely products-specific. Source of truth:
 * spec 0017 frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { AttributeDataType } from '@/features/attributes/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** A typed dynamic-attribute field value, resolved from the matching `value_*` column. */
export type AttributeFieldValue = string | number | boolean | null

/** Product classification (spec 0017). SERVICE-only for now; mirrors the `ProductType` enum. */
export type ProductType = 'SERVICE'

/** Minimal category projection hydrating the product's grid/detail. */
export interface ProductCategorySummary {
  id: number
  name: string
}

/** A single dynamic attribute value hydrated on the product (GET show). */
export interface ProductAttributeValue {
  attribute_id: number
  code: string
  name: string
  data_type: AttributeDataType
  value: AttributeFieldValue
  option_id?: number | null
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
  attributes: ProductAttributeValue[]
  created_at: string
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

/** A single dynamic attribute value sent to the backend. */
export interface ProductAttributeValueInput {
  attribute_id: number
  value: AttributeFieldValue
}

/**
 * Payload for POST /products (create). `attributes` carries only the
 * currently-generated dynamic fields (spec AC-024).
 */
export interface CreateProductPayload {
  name: string
  description?: string | null
  cost: number
  price: number
  category_id: number
  product_type: ProductType
  attributes?: ProductAttributeValueInput[]
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /products/{id} (partial update). Generic fields are
 * sparse (only what changed); `attributes`, when present, is a full-replace
 * of the dynamic values.
 */
export type UpdateProductPayload = Partial<CreateProductPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `ProductForm` component (mirrors `BusinessFunctionFormMode`).
 */
export type ProductFormMode = { type: 'create' } | { type: 'edit'; product: ProductDetailWithPermissions }
