import type { CreateTagPayload, TagDetail, UpdateTagPayload } from '@/features/tags/types'
import type { TagFormValues } from '@/features/tags/use-tag-form'

/** Builds the create payload: the single `name` field. */
export function buildCreatePayload(values: TagFormValues): CreateTagPayload {
  return { name: values.name }
}

/**
 * Builds a partial PATCH payload carrying only the `name` field when it
 * actually changed from the original tag.
 */
export function buildUpdatePayload(values: TagFormValues, original: TagDetail): UpdateTagPayload {
  const payload: UpdateTagPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }

  return payload
}
