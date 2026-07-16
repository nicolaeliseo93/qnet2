import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'

/**
 * Contact types the duplicate check matches on (spec 0037): a deliberate
 * subset of the full contact type enum (`email`/`pec`/`website`/... exist,
 * but only these three are duplicate-check criteria, mirroring the import
 * dedup rules of spec 0036).
 */
export type ReferentDuplicateContactType = 'email' | 'phone' | 'mobile'

/** One contact criterion sent to the duplicate-check endpoint. */
export interface ReferentDuplicateContact {
  type: ReferentDuplicateContactType
  value: string
}

/** POST /referents/duplicate-check request body. At least one criterion is required server-side (422 otherwise). */
export interface ReferentDuplicateCheckPayload {
  tax_code?: string
  contacts?: ReferentDuplicateContact[]
}

/**
 * One existing referent matching the submitted criteria. Deliberately carries
 * only `{ referent_id, name, matched_on }` — never the raw contact/tax_code
 * value of the match, so the check cannot become a PII exfiltration channel.
 */
export interface ReferentDuplicateMatch {
  referent_id: number
  name: string
  matched_on: string[]
}

interface ReferentDuplicateCheckResponse {
  matches: ReferentDuplicateMatch[]
}

/**
 * Query key of a duplicate check for a given set of (already trimmed)
 * criteria, so identical inputs share the cache and a criteria change starts
 * a fresh request.
 */
export function referentDuplicateCheckQueryKey(
  taxCode: string,
  contacts: ReferentDuplicateContact[],
) {
  return ['referents', 'duplicate-check', { taxCode, contacts }] as const
}

/**
 * Checks whether the given tax code / contacts already belong to an existing
 * referent. Read-only, non-blocking (spec 0037): the caller only ever uses
 * the result to render a warning, never to gate the save.
 */
export async function checkReferentDuplicates(
  payload: ReferentDuplicateCheckPayload,
): Promise<ReferentDuplicateCheckResponse> {
  const { data } = await apiClient.post<ApiResponse<ReferentDuplicateCheckResponse>>(
    '/referents/duplicate-check',
    payload,
  )
  return data.data
}
