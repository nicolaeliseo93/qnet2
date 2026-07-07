import type {
  AttributeAssignmentInput,
  CreateProductCategoryPayload,
  ProductCategoryDetail,
  UpdateProductCategoryPayload,
} from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

function sameAssignments(a: AttributeAssignmentInput[], b: AttributeAssignmentInput[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const key = (assignment: AttributeAssignmentInput) =>
    `${assignment.attribute_id}:${assignment.is_required ?? false}:${assignment.sort_order ?? 0}`
  const bKeys = new Set(b.map(key))
  return a.every((assignment) => bKeys.has(key(assignment)))
}

/** Builds the create payload: generic fields + the own attribute assignments. */
export function buildCreatePayload(
  values: ProductCategoryFormValues,
): CreateProductCategoryPayload {
  return {
    name: values.name,
    parent_id: values.parent_id,
    inherits_attributes: values.inherits_attributes,
    description: values.description,
    attributes: values.attributes,
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original category (spec 0017 AC-010). `attributes` is a full-replace sync:
 * sent whenever the assignment set differs (by attribute/is_required/sort_order).
 */
export function buildUpdatePayload(
  values: ProductCategoryFormValues,
  original: ProductCategoryDetail,
): UpdateProductCategoryPayload {
  const payload: UpdateProductCategoryPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.parent_id !== original.parent_id) {
    payload.parent_id = values.parent_id
  }
  if (values.inherits_attributes !== original.inherits_attributes) {
    payload.inherits_attributes = values.inherits_attributes
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }

  const originalAssignments: AttributeAssignmentInput[] = original.attributes.map((a) => ({
    attribute_id: a.attribute_id,
    is_required: a.is_required,
    sort_order: a.sort_order,
  }))
  if (!sameAssignments(values.attributes, originalAssignments)) {
    payload.attributes = values.attributes
  }

  return payload
}
