import { isEmptyCustomFieldValue, isEqualCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Builds the wire `custom_fields` object for both write paths (spec 0021
 * AC-023). Keys here are already RAW (`rawKey`, un-namespaced) — this is the
 * exact shape the `custom_fields` request body expects.
 */
type CustomFieldValues = Record<string, CustomFieldValue>

/** POST create: every VALUED field (empty/unset ones are simply omitted, not sent as `null`). */
export function buildCustomFieldsCreate(values: CustomFieldValues): CustomFieldValues {
  const payload: CustomFieldValues = {}
  for (const [key, value] of Object.entries(values)) {
    if (!isEmptyCustomFieldValue(value)) {
      payload[key] = value
    }
  }
  return payload
}

/** PATCH update: only the keys that actually changed from `original` (sparse merge, spec AC-012). */
export function buildCustomFieldsUpdate(
  values: CustomFieldValues,
  original: CustomFieldValues,
): CustomFieldValues {
  const payload: CustomFieldValues = {}
  for (const [key, value] of Object.entries(values)) {
    if (!isEqualCustomFieldValue(value, original[key] ?? null)) {
      payload[key] = value
    }
  }
  return payload
}
