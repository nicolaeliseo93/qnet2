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

/** Builds the create payload: `name`, `color` and `sort_order`. */
export function buildCreatePayload(values: PipelineStatusFormValues): CreatePipelineStatusPayload {
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
