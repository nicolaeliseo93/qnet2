import { useInfiniteQuery } from '@tanstack/react-query'
import {
  PROJECT_CARDS_PAGE_SIZE,
  fetchProjectCards,
  projectCardsQueryKey,
} from '@/features/projects/api'
import type { ProjectCardListParams } from '@/features/projects/types'

/**
 * Infinite-scroll list of project cards (spec 0026 AC-007). Follows the
 * offset/limit convention already used by the notifications panel
 * (`features/notifications/use-notifications.ts`): the next page offset is
 * derived from the backend pagination envelope, and `getNextPageParam`
 * returns `undefined` once every row is loaded, which sets `hasNextPage` to
 * `false`.
 */
export function useProjectCards(filters: Pick<ProjectCardListParams, 'search' | 'project_status_id'> = {}) {
  return useInfiniteQuery({
    queryKey: projectCardsQueryKey(filters),
    queryFn: ({ pageParam }) =>
      fetchProjectCards({ ...filters, offset: pageParam, limit: PROJECT_CARDS_PAGE_SIZE }),
    initialPageParam: 0,
    getNextPageParam: (lastPage) => {
      const { offset, limit, total } = lastPage.pagination
      const nextOffset = offset + limit
      return nextOffset < total ? nextOffset : undefined
    },
  })
}
