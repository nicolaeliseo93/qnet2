import type {
  CreateEaSectorPayload,
  EaSectorDetail,
  UpdateEaSectorPayload,
} from '@/features/ea-sectors/types'
import type { EaSectorFormValues } from '@/features/ea-sectors/use-ea-sector-form'

/** Builds the create payload: `name` + `parent_id` (null = root sector). */
export function buildCreatePayload(values: EaSectorFormValues): CreateEaSectorPayload {
  return {
    name: values.name,
    parent_id: values.parent_id,
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original sector (spec 0018 AC-019).
 */
export function buildUpdatePayload(
  values: EaSectorFormValues,
  original: EaSectorDetail,
): UpdateEaSectorPayload {
  const payload: UpdateEaSectorPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.parent_id !== original.parent_id) {
    payload.parent_id = values.parent_id
  }

  return payload
}
