import type { CreateTagPayload, TagDetail, UpdateTagPayload } from '@/features/tags/types'
import type { TagFormValues } from '@/features/tags/use-tag-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** Builds the create payload: the single `name` field. */
export function buildCreatePayload(values: TagFormValues): CreateTagPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    name: values.name,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only the `name` field when it
 * actually changed from the original tag.
 */
export function buildUpdatePayload(values: TagFormValues, original: TagDetail): UpdateTagPayload {
  const payload: UpdateTagPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
