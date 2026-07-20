import type {
  CreateOpportunityStatusPayload,
  OpportunityStatusDetail,
  UpdateOpportunityStatusPayload,
} from '@/features/opportunity-statuses/types'
import type { OpportunityStatusFormValues } from '@/features/opportunity-statuses/use-opportunity-status-form'

/** Maps the form's `color` (empty string = unset) to the backend's nullable value. */
function colorValue(color: string): string | null {
  return color === '' ? null : color
}

/** Builds the create payload: `name`, `color` and `group` (`sort_order` is server-managed, D-5). */
export function buildCreatePayload(
  values: OpportunityStatusFormValues,
): CreateOpportunityStatusPayload {
  return {
    name: values.name,
    color: colorValue(values.color),
    group: values.group,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original opportunity status.
 */
export function buildUpdatePayload(
  values: OpportunityStatusFormValues,
  original: OpportunityStatusDetail,
): UpdateOpportunityStatusPayload {
  const payload: UpdateOpportunityStatusPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (colorValue(values.color) !== original.color) {
    payload.color = colorValue(values.color)
  }
  if (values.group !== original.group) {
    payload.group = values.group
  }

  return payload
}
