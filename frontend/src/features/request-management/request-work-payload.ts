import { isEqualCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestWorkPanel, UpdateRequestWorkPayload } from '@/features/request-management/types'

/**
 * True when at least one applicable attribute's current value differs from
 * the panel's loaded one. `attribute_values` is a merge/replace-whole-map
 * field server-side (spec 0049 data_contract): there is no per-code sparse
 * diff to compute, only whether the map as a whole needs resending.
 */
function attributeValuesChanged(
  current: Record<string, CustomFieldValue>,
  original: Record<string, unknown>,
  codes: string[],
): boolean {
  return codes.some(
    (code) => !isEqualCustomFieldValue(current[code] ?? null, (original[code] as CustomFieldValue) ?? null),
  )
}

/**
 * Builds the sparse PATCH payload (AC-062): only `opportunity_workflow_status_id`
 * and/or `attribute_values` are included, each only when it actually changed
 * from the loaded `panel`.
 */
export function buildRequestWorkPayload(
  values: RequestWorkFormValues,
  panel: RequestWorkPanel,
): UpdateRequestWorkPayload {
  const payload: UpdateRequestWorkPayload = {}

  const originalWorkflowStatusId = panel.workflow_status?.id ?? null
  if (values.opportunity_workflow_status_id !== originalWorkflowStatusId) {
    payload.opportunity_workflow_status_id = values.opportunity_workflow_status_id
  }

  const codes = panel.applicable_attributes.map((attribute) => attribute.code)
  if (attributeValuesChanged(values.attribute_values, panel.attribute_values, codes)) {
    payload.attribute_values = values.attribute_values
  }

  return payload
}
