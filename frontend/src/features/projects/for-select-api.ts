import { apiClient } from '@/api/client'
import { FOR_SELECT_PAGE_SIZE } from '@/features/for-select/api'
import type { ForSelectItem, ForSelectParams, PaginatedResponse } from '@/features/for-select/types'

/** Resource segment for the projects for-select endpoint (spec 0023). */
export const PROJECTS_FOR_SELECT_RESOURCE = 'projects'

/** A relation's `{id, label}` projection inside `ProjectForSelectItem.meta`. */
export interface ProjectForSelectRelation {
  id: number
  label: string
}

/**
 * The `meta` block carried by every `/projects/for-select` item (spec 0023):
 * the Campaign form's default-population source when a Project is linked
 * (AC-042) — no extra request, the picker's own response already carries it.
 * `total_budget`/`allocated_budget`/`remaining_budget` are decimal columns
 * cast `decimal:2`, serialized as numeric strings.
 */
export interface ProjectForSelectMeta {
  registry: ProjectForSelectRelation | null
  source: ProjectForSelectRelation | null
  partner: ProjectForSelectRelation | null
  project_status: ProjectForSelectRelation
  business_function: ProjectForSelectRelation | null
  state: ProjectForSelectRelation | null
  product_category: ProjectForSelectRelation | null
  total_budget: string | null
  allocated_budget: string
  remaining_budget: string | null
}

/** A single project option as returned by `GET /api/projects/for-select`, label = "PRJ-0001 — Name". */
export interface ProjectForSelectItem extends ForSelectItem {
  meta: ProjectForSelectMeta
}

/**
 * Fetches a page of project options (with their `meta` default-population
 * block) from `GET /api/projects/for-select`. NOT a thin call to the generic
 * `fetchForSelect` (typed to the meta-less `ForSelectItem`): this is the same
 * envelope, typed with the richer `ProjectForSelectItem` shape the Campaign
 * form needs.
 */
export async function fetchProjectsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ProjectForSelectItem>> {
  const { search, offset = 0, limit = FOR_SELECT_PAGE_SIZE, ids } = params
  const { data } = await apiClient.get<PaginatedResponse<ProjectForSelectItem>>(
    `/${PROJECTS_FOR_SELECT_RESOURCE}/for-select`,
    {
      params: {
        offset,
        limit,
        ...(search ? { search } : {}),
        ...(ids && ids.length > 0 ? { ids } : {}),
      },
      paramsSerializer: { indexes: true },
    },
  )
  return data
}
