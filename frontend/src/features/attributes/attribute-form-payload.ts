import type {
  AttributeDetail,
  AttributeOptionInput,
  CreateAttributePayload,
  UpdateAttributePayload,
} from '@/features/attributes/types'
import type { AttributeFormValues } from '@/features/attributes/use-attribute-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** Maps the form's option rows to the wire shape, assigning `sort_order` from array position. */
function buildOptions(values: AttributeFormValues): AttributeOptionInput[] | undefined {
  if (values.data_type !== 'ENUM') {
    return undefined
  }
  return values.options.map((option, index) => ({
    value: option.value,
    label: option.label,
    sort_order: index,
  }))
}

/** Builds the create payload: generic fields + options (only when ENUM). */
export function buildCreatePayload(values: AttributeFormValues): CreateAttributePayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    code: values.code,
    name: values.name,
    data_type: values.data_type,
    options: buildOptions(values),
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original attribute (spec 0017 AC-004). `options` is a full-replace: sent
 * whenever the ENUM option set differs from the original (by value/label/
 * order), or whenever `data_type` itself changed.
 */
export function buildUpdatePayload(
  values: AttributeFormValues,
  original: AttributeDetail,
): UpdateAttributePayload {
  const payload: UpdateAttributePayload = {}

  if (values.code !== original.code) {
    payload.code = values.code
  }
  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.data_type !== original.data_type) {
    payload.data_type = values.data_type
  }

  const nextOptions = buildOptions(values)
  const originalOptions = original.options.map(({ value, label, sort_order }) => ({
    value,
    label,
    sort_order,
  }))
  if (
    values.data_type !== original.data_type ||
    JSON.stringify(nextOptions ?? []) !== JSON.stringify(originalOptions)
  ) {
    payload.options = nextOptions
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
