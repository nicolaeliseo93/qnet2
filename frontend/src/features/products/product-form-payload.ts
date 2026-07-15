import type {
  CreateProductPayload,
  ProductDetail,
  UpdateProductPayload,
} from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** Builds the create payload: generic fields + valued custom fields. */
export function buildCreatePayload(values: ProductFormValues): CreateProductPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    name: values.name,
    description: values.description,
    // cost/price/category_id are validated non-null by the schema's
    // required-value superRefine before submit.
    cost: values.cost as number,
    price: values.price as number,
    category_id: values.category_id as number,
    product_type: values.product_type,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original product (spec 0017 AC-024).
 */
export function buildUpdatePayload(
  values: ProductFormValues,
  original: ProductDetail,
): UpdateProductPayload {
  const payload: UpdateProductPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.cost !== original.cost) {
    // See buildCreatePayload: validated non-null by the schema's
    // required-value superRefine before submit.
    payload.cost = values.cost as number
  }
  if (values.price !== original.price) {
    payload.price = values.price as number
  }
  if (values.category_id !== original.category_id) {
    payload.category_id = values.category_id as number
  }
  if (values.product_type !== original.product_type) {
    payload.product_type = values.product_type
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
