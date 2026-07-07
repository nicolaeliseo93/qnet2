import type {
  CreateEaSectorPayload,
  EaSectorDetail,
  UpdateEaSectorPayload,
} from '@/features/ea-sectors/types'
import type { EaSectorFormValues } from '@/features/ea-sectors/use-ea-sector-form'

/** Builds the create payload: `name` + `parent_id` (null = root sector) + `tag_ids`. */
export function buildCreatePayload(values: EaSectorFormValues): CreateEaSectorPayload {
  return {
    name: values.name,
    parent_id: values.parent_id,
    tag_ids: values.tag_ids,
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
  if (!sameIdSet(values.tag_ids, original.tag_ids)) {
    payload.tag_ids = values.tag_ids
  }

  return payload
}

/** Order-insensitive comparison of two id lists (mirrors `businessFunctions`). */
function sameIdSet(a: number[], b: number[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const set = new Set(b)
  return a.every((id) => set.has(id))
}
