/**
 * EA Sectors CRUD + tree types. The generic table types live in
 * `features/table/types.ts`; this file holds only what is genuinely
 * ea-sectors-specific. Source of truth: spec 0018 frozen `data_contract`.
 * Sectors have no dedicated grid tree view: the hierarchy tree is used only
 * to feed the parent picker (see `flatten-tree.ts`), the LIST surface is the
 * generic server-driven table.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** A node of the sector tree, as returned by `GET /ea-sectors/tree`. */
export interface EaSectorTreeNode {
  id: number
  name: string
  parent_id: number | null
  children: EaSectorTreeNode[]
}

/**
 * Single sector detail returned by GET/POST/PATCH /ea-sectors (envelope
 * `data`). Matches `EaSectorResource`.
 */
export interface EaSectorDetail {
  id: number
  name: string
  parent_id: number | null
  parent: { id: number; name: string } | null
  created_at: string
}

/**
 * An `EaSectorDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /ea-sectors/{id}`. Used to seed
 * the edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface EaSectorDetailWithPermissions extends EaSectorDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /ea-sectors (create). */
export interface CreateEaSectorPayload {
  name: string
  parent_id?: number | null
}

/** Payload for PATCH /ea-sectors/{id} (partial update). */
export type UpdateEaSectorPayload = Partial<CreateEaSectorPayload>

/**
 * Discriminated form mode. Create optionally pre-selects a parent (an
 * "add sub-sector" affordance on a given tree node, mirrors
 * `ProductCategoryFormMode`).
 */
export type EaSectorFormMode =
  | { type: 'create'; parentId: number | null }
  | { type: 'edit'; sector: EaSectorDetailWithPermissions }
