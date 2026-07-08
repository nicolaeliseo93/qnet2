import type {
  CreateSectorPayload,
  SectorDetail,
  UpdateSectorPayload,
} from '@/features/sectors/types'
import type { SectorFormValues } from '@/features/sectors/use-sector-form'

/** Builds the create payload: `name` + `parent_id` (null = root sector). */
export function buildCreatePayload(values: SectorFormValues): CreateSectorPayload {
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
  values: SectorFormValues,
  original: SectorDetail,
): UpdateSectorPayload {
  const payload: UpdateSectorPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.parent_id !== original.parent_id) {
    payload.parent_id = values.parent_id
  }

  return payload
}
