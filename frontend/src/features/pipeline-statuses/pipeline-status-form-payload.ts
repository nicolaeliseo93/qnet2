import type {
  CreatePipelineStatusPayload,
  PipelineStatusDetail,
  UpdatePipelineStatusPayload,
} from '@/features/pipeline-statuses/types'
import type { PipelineStatusFormValues } from '@/features/pipeline-statuses/use-pipeline-status-form'
import {
  buildCustomFieldsCreate,
  buildCustomFieldsUpdate,
} from '@/features/custom-fields/custom-fields-payload'

/** Maps the form's `color` (empty string = unset) to the backend's nullable value. */
function colorValue(color: string): string | null {
  return color === '' ? null : color
}

/** Builds the create payload: `name`, `color` and `status_group_id` (`sort_order` is server-managed, spec 0039 D-5). */
export function buildCreatePayload(values: PipelineStatusFormValues): CreatePipelineStatusPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    name: values.name,
    color: colorValue(values.color),
    status_group_id: values.status_group_id,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original project status.
 */
export function buildUpdatePayload(
  values: PipelineStatusFormValues,
  original: PipelineStatusDetail,
): UpdatePipelineStatusPayload {
  const payload: UpdatePipelineStatusPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (colorValue(values.color) !== original.color) {
    payload.color = colorValue(values.color)
  }
  if (values.status_group_id !== original.status_group_id) {
    payload.status_group_id = values.status_group_id
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
