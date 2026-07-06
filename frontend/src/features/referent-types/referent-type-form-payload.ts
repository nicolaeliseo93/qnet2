import type {
  CreateReferentTypePayload,
  ReferentTypeDetail,
  UpdateReferentTypePayload,
} from '@/features/referent-types/types'
import type { ReferentTypeFormValues } from '@/features/referent-types/use-referent-type-form'

/** Builds the create payload: the single `name` field. */
export function buildCreatePayload(values: ReferentTypeFormValues): CreateReferentTypePayload {
  return { name: values.name }
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

  return payload
}
