import type {
  CreateStatusGroupPayload,
  StatusGroupDetail,
  UpdateStatusGroupPayload,
} from '@/features/status-groups/types'
import type { StatusGroupFormValues } from '@/features/status-groups/use-status-group-form'
import {
  buildCustomFieldsCreate,
  buildCustomFieldsUpdate,
} from '@/features/custom-fields/custom-fields-payload'

/** Maps the form's `color` (empty string = unset) to the backend's nullable value. */
function colorValue(color: string): string | null {
  return color === '' ? null : color
}

/** Builds the create payload: `name`, `color` and `sort_order`. */
export function buildCreatePayload(values: StatusGroupFormValues): CreateStatusGroupPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    name: values.name,
    color: colorValue(values.color),
    sort_order: values.sort_order,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original status group.
 */
export function buildUpdatePayload(
  values: StatusGroupFormValues,
  original: StatusGroupDetail,
): UpdateStatusGroupPayload {
  const payload: UpdateStatusGroupPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (colorValue(values.color) !== original.color) {
    payload.color = colorValue(values.color)
  }
  if (values.sort_order !== original.sort_order) {
    payload.sort_order = values.sort_order
  }

  const customFields = buildCustomFieldsUpdate(
    values.custom_fields,
    original.custom_fields ?? {},
  )
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
