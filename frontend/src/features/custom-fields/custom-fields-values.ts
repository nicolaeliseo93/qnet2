import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Value-level helpers shared by the dynamic schema and the payload builders
 * (spec 0021 AC-023) so "what counts as empty/changed" is defined exactly
 * once.
 */

/** `null`/`undefined`, a blank string, or an empty array all count as "not set". `false` does not. */
export function isEmptyCustomFieldValue(value: unknown): boolean {
  if (value === null || value === undefined) {
    return true
  }
  if (typeof value === 'string') {
    return value.trim().length === 0
  }
  if (Array.isArray(value)) {
    return value.length === 0
  }
  return false
}

/** Order-independent equality for arrays (enum multiselect / relation many), strict for scalars. */
export function isEqualCustomFieldValue(a: CustomFieldValue, b: CustomFieldValue): boolean {
  if (Array.isArray(a) && Array.isArray(b)) {
    if (a.length !== b.length) {
      return false
    }
    const sortedA = [...a].sort()
    const sortedB = [...b].sort()
    return sortedA.every((item, index) => item === sortedB[index])
  }
  return a === b
}
