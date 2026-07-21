import { useQuery, type QueryClient } from '@tanstack/react-query'
import { fetchProjectsForSelect, type ProjectForSelectMeta } from '@/features/projects/for-select-api'
import type { ProjectGeoMeta } from '@/features/campaigns/campaign-geo'

/**
 * Widens `ProjectForSelectMeta` with its (spec 0027 D-5) `geo` block. FE-1
 * dependency: `ProjectForSelectMeta` itself does not declare `geo` yet — see
 * `campaign-geo.ts`'s `ProjectGeoMeta` doc. Kept optional so this lane
 * type-checks and runs regardless of the other lane's landing order.
 */
export type ProjectMetaWithGeo = ProjectForSelectMeta & { geo?: ProjectGeoMeta }

/**
 * Query key of a single project's `for-select` `meta` block, keyed by id.
 * Shared by the reactive display hook below and the imperative prefill fetch
 * (`fetchCampaignProjectMeta`) so both read/write the same TanStack Query
 * cache entry and a selection never triggers a duplicate request.
 */
export function campaignProjectMetaQueryKey(projectId: number | null) {
  return ['projects', 'meta-for-campaign', projectId] as const
}

/**
 * Fetches the project's `for-select` item by id and extracts its `meta` block.
 * The endpoint returns the first page PLUS the requested id appended at the end
 * (`appendHydratedIds`), so the item must be matched by id — never `items[0]`,
 * which is the first project by name, not the selected one.
 */
async function loadProjectMeta(projectId: number): Promise<ProjectMetaWithGeo | null> {
  const page = await fetchProjectsForSelect({ ids: [projectId] })
  return page.items.find((item) => item.id === projectId)?.meta ?? null
}

/**
 * Reactive, DISPLAY-ONLY read of the linked project's `meta` (BR-3's
 * remaining-budget hint next to the campaign's own budget field). Never
 * writes to the form: prefilling the campaign's fields happens exactly once,
 * on selection, via {@link fetchCampaignProjectMeta} — re-running this query
 * (e.g. a background refetch) must never silently overwrite a user's edits.
 */
export function useCampaignProjectMeta(projectId: number | null) {
  return useQuery({
    queryKey: campaignProjectMetaQueryKey(projectId),
    queryFn: () => loadProjectMeta(projectId as number),
    enabled: projectId !== null,
  })
}

/**
 * Imperative one-shot fetch used by the Project picker's `onChange` (AC-042)
 * to read the newly selected project's default-population `meta` exactly once,
 * as a direct consequence of the user's action — never as a render-time
 * effect. Shares its cache entry with {@link useCampaignProjectMeta}.
 */
export function fetchCampaignProjectMeta(
  queryClient: QueryClient,
  projectId: number,
): Promise<ProjectMetaWithGeo | null> {
  return queryClient.fetchQuery({
    queryKey: campaignProjectMetaQueryKey(projectId),
    queryFn: () => loadProjectMeta(projectId),
  })
}
