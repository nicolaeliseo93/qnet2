/**
 * Sectors CRUD + tree types. The generic table types live in
 * `features/table/types.ts`; this file holds only what is genuinely
 * sectors-specific. Source of truth: spec 0018 frozen `data_contract`.
 * Sectors have no dedicated grid tree view: the hierarchy tree is used only
 * to feed the parent picker (see `flatten-tree.ts`), the LIST surface is the
 * generic server-driven table.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** A node of the sector tree, as returned by `GET /sectors/tree`. */
export interface SectorTreeNode {
  id: number
  name: string
  parent_id: number | null
  children: SectorTreeNode[]
}

/**
 * Single sector detail returned by GET/POST/PATCH /sectors (envelope
 * `data`). Matches `SectorResource`.
 */
export interface SectorDetail {
  id: number
  name: string
  parent_id: number | null
  parent: { id: number; name: string } | null
  created_at: string
}

/**
 * A `SectorDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /sectors/{id}`. Used to seed
 * the edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface SectorDetailWithPermissions extends SectorDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /sectors (create). */
export interface CreateSectorPayload {
  name: string
  parent_id?: number | null
}

/** Payload for PATCH /sectors/{id} (partial update). */
export type UpdateSectorPayload = Partial<CreateSectorPayload>

/**
 * Discriminated form mode. Create optionally pre-selects a parent (an
 * "add sub-sector" affordance on a given tree node, mirrors
 * `ProductCategoryFormMode`).
 */
export type SectorFormMode =
  | { type: 'create'; parentId: number | null }
  | { type: 'edit'; sector: SectorDetailWithPermissions }
