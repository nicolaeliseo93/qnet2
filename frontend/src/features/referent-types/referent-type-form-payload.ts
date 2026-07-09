import type {
  CreateReferentTypePayload,
  ReferentTypeDetail,
  UpdateReferentTypePayload,
} from '@/features/referent-types/types'
import type { ReferentTypeFormValues } from '@/features/referent-types/use-referent-type-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

/** Builds the create payload: the single `name` field. */
export function buildCreatePayload(values: ReferentTypeFormValues): CreateReferentTypePayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    name: values.name,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only the `name` field when it
 * actually changed from the original referent type.
 */
export function buildUpdatePayload(
  values: ReferentTypeFormValues,
  original: ReferentTypeDetail,
): UpdateReferentTypePayload {
  const payload: UpdateReferentTypePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
