import type {
  CreateSourcePayload,
  SourceDetail,
  UpdateSourcePayload,
} from '@/features/sources/types'
import type { SourceFormValues } from '@/features/sources/use-source-form'

/** Builds the create payload: the single `name` field. */
export function buildCreatePayload(values: SourceFormValues): CreateSourcePayload {
  return { name: values.name }
}

/**
 * Builds a partial PATCH payload carrying only the `name` field when it
 * actually changed from the original source.
 */
export function buildUpdatePayload(
  values: SourceFormValues,
  original: SourceDetail,
): UpdateSourcePayload {
  const payload: UpdateSourcePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }

  return payload
}
