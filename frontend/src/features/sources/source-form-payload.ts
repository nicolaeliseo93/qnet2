import type {
  CreateSourcePayload,
  SourceDetail,
  UpdateSourcePayload,
} from '@/features/sources/types'
import type { SourceFormValues } from '@/features/sources/use-source-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** Builds the create payload: the single `name` field. */
export function buildCreatePayload(values: SourceFormValues): CreateSourcePayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    name: values.name,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only the `name` field when it
 * actually changed from the original source.
 */
export function buildUpdatePayload(
  values: SourceFormValues,
  original: SourceDetail,
): UpdateSourcePayload {
  const payload: UpdateSourcePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
