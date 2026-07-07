import type {
  AttributeFieldValue,
  CreateProductPayload,
  ProductAttributeValueInput,
  ProductDetail,
  UpdateProductPayload,
} from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

/** Converts the form's `{attribute_id: value}` record into the wire array shape. */
function attributesRecordToArray(
  record: Record<string, AttributeFieldValue>,
): ProductAttributeValueInput[] {
  return Object.entries(record).map(([attributeId, value]) => ({
    attribute_id: Number(attributeId),
    value,
  }))
}

/** Builds the create payload: generic fields + the currently-generated dynamic attributes. */
export function buildCreatePayload(values: ProductFormValues): CreateProductPayload {
  return {
    name: values.name,
    description: values.description,
    // cost/price/category_id are validated non-null by the schema's
    // required-value superRefine before submit.
    cost: values.cost as number,
    price: values.price as number,
    category_id: values.category_id as number,
    product_type: values.product_type,
    attributes: attributesRecordToArray(values.attributes),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original product (spec 0017 AC-024). `attributes` is sent only when the
 * dynamic fields were actually touched (`attributesDirty`, read from RHF's
 * `formState.dirtyFields` by the caller) — never inferred from equality,
 * since an untouched category still carries its full effective-attributes set.
 */
export function buildUpdatePayload(
  values: ProductFormValues,
  original: ProductDetail,
  attributesDirty: boolean,
): UpdateProductPayload {
  const payload: UpdateProductPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.cost !== original.cost) {
    payload.cost = values.cost
  }
  if (values.price !== original.price) {
    payload.price = values.price
  }
  if (values.category_id !== original.category_id) {
    payload.category_id = values.category_id as number
  }
  if (values.product_type !== original.product_type) {
    payload.product_type = values.product_type
  }
  if (attributesDirty) {
    payload.attributes = attributesRecordToArray(values.attributes)
  }

  return payload
}
