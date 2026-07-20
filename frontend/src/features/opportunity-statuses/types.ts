/**
 * Opportunity statuses CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely opportunity-statuses-specific — the resource and its
 * create/update payloads. Source of truth: spec 0043 frozen `data_contract`.
 * This module has no custom fields (spec 0043 scope: out).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { StatusGroupValue, SystemStatusKey } from '@/features/status-reorder/types'

/**
 * Single opportunity status detail returned by GET/POST/PATCH
 * /opportunity-statuses (envelope `data`). Matches `OpportunityStatusResource`.
 * `sort_order` is server-managed (D-5): read-only, never accepted on write.
 * `system_key` marks the three system rows ("Nuova"/"Chiusa con
 * successo"/"Persa"), whose `group` is fixed and whose delete/reorder are
 * server-blocked.
 */
export interface OpportunityStatusDetail {
  id: number
  name: string
  /** Palette token (e.g. "blue"), or null when unset. */
  color: string | null
  sort_order: number
  system_key: SystemStatusKey
  group: StatusGroupValue
  created_at: string
}

/**
 * An `OpportunityStatusDetail` carrying the actor's authorization metadata
 * for this instance (spec 0004), as returned by `GET /opportunity-statuses/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface OpportunityStatusDetailWithPermissions extends OpportunityStatusDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /opportunity-statuses (create). `sort_order` is NOT
 * accepted (D-5): placement is automatic, always last among the customs.
 */
export interface CreateOpportunityStatusPayload {
  name: string
  color?: string | null
  group: StatusGroupValue
}

/**
 * Payload for PATCH /opportunity-statuses/{id} (partial update). Every field
 * is optional so the request only carries what actually changed.
 */
export type UpdateOpportunityStatusPayload = Partial<CreateOpportunityStatusPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `OpportunityStatusForm` component.
 */
export type OpportunityStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; opportunityStatus: OpportunityStatusDetailWithPermissions }
