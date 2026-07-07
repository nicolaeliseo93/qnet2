/**
 * Product Categories CRUD + tree types. The generic table types live in
 * `features/table/types.ts`; this file holds only what is genuinely
 * product-categories-specific. Source of truth: spec 0017 frozen
 * `data_contract`. Categories have no grid (they use a dedicated tree view),
 * so there is no `TableRow`-shaped row type here.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { AttributeDataType } from '@/features/attributes/types'

/** A node of the category tree, as returned by `GET /product-categories/tree`. */
export interface ProductCategoryTreeNode {
  id: number
  name: string
  parent_id: number | null
  children: ProductCategoryTreeNode[]
  attributes_count: number
  products_count: number
}

/** A category's own attribute assignment (pivot `attribute_category`). */
export interface ProductCategoryAttributeAssignment {
  attribute_id: number
  code: string
  name: string
  data_type: AttributeDataType
  is_required: boolean
  sort_order: number
}

/** An attribute inherited from an ancestor category (read-only in the form). */
export interface ProductCategoryInheritedAttribute {
  attribute_id: number
  code: string
  name: string
  data_type: AttributeDataType
  is_required: boolean
}

/**
 * Single category detail returned by GET/POST/PATCH /product-categories
 * (envelope `data`). Matches `ProductCategoryResource`.
 */
export interface ProductCategoryDetail {
  id: number
  name: string
  parent_id: number | null
  parent: { id: number; name: string } | null
  /** When false the category ignores its ancestry (barrier): no inherited attributes for it or its descendants. */
  inherits_attributes: boolean
  description: string | null
  attributes: ProductCategoryAttributeAssignment[]
  inherited_attributes: ProductCategoryInheritedAttribute[]
  created_at: string
}

/**
 * A `ProductCategoryDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /product-categories/{id}`.
 * Used to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface ProductCategoryDetailWithPermissions extends ProductCategoryDetail {
  permissions: ResourcePermissions
}

/**
 * A single attribute's effective assignment for a category, as returned by
 * `GET /product-categories/{id}/effective-attributes` — the product form's
 * dynamic-fields source (own assignments UNION every ancestor's).
 */
export interface EffectiveAttribute {
  id: number
  code: string
  name: string
  data_type: AttributeDataType
  is_required: boolean
  inherited: boolean
  options: { value: string; label: string }[]
}

/** A single attribute-to-category assignment sent to the backend (full-replace sync). */
export interface AttributeAssignmentInput {
  attribute_id: number
  is_required?: boolean
  sort_order?: number
}

/** Payload for POST /product-categories (create). */
export interface CreateProductCategoryPayload {
  name: string
  parent_id?: number | null
  inherits_attributes?: boolean
  description?: string | null
  attributes?: AttributeAssignmentInput[]
}

/** Payload for PATCH /product-categories/{id} (partial update). */
export type UpdateProductCategoryPayload = Partial<CreateProductCategoryPayload>

/**
 * Discriminated form mode. Create optionally pre-selects a parent (the tree's
 * "add subcategory" action on a given node).
 */
export type ProductCategoryFormMode =
  | { type: 'create'; parentId: number | null }
  | { type: 'edit'; category: ProductCategoryDetailWithPermissions }
