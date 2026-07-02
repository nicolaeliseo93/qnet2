import { useInfiniteQuery } from '@tanstack/react-query'
import { fetchForSelect, FOR_SELECT_PAGE_SIZE } from '@/features/for-select/api'
import { forSelectKeys } from '@/features/for-select/query-keys'
import type { ForSelectItem } from '@/features/for-select/types'

interface UseForSelectOptions {
  /** Resource segment of the endpoint, e.g. `users` → `/users/for-select`. */
  resource: string
  /** Debounced server-side search term. */
  search: string
  /** Ids of already-selected values to hydrate on the first page (edit mode). */
  ids?: number[]
  /** Gates the query so it only runs when the consumer needs the data. */
  enabled?: boolean
}

/**
 * Reusable infinite query for any entity-backed for-select endpoint (ADR 0011).
 * Offset-based pagination mirroring the notifications list: `getNextPageParam`
 * derives the next offset from the envelope and returns undefined once every row
 * is loaded. The selected `ids` hydrate only the first page (they are explicit
 * and bypass `search`); they are intentionally excluded from the query key so a
 * selection change never refetches the whole list.
 */
export function useForSelect({
  resource,
  search,
  ids,
  enabled = true,
}: UseForSelectOptions) {
  return useInfiniteQuery({
    queryKey: forSelectKeys.list(resource, search),
    queryFn: ({ pageParam }) =>
      fetchForSelect(resource, {
        search,
        offset: pageParam,
        limit: FOR_SELECT_PAGE_SIZE,
        // Hydrate selected values only on the first page to label current
        // selections that fall outside the searched window.
        ids: pageParam === 0 ? ids : undefined,
      }),
    initialPageParam: 0,
    getNextPageParam: (lastPage) => {
      const { offset, limit, total } = lastPage.pagination
      const nextOffset = offset + limit
      return nextOffset < total ? nextOffset : undefined
    },
    enabled,
  })
}

/** Flattens the loaded pages into a single de-duplicated option list (by id). */
export function flattenForSelectPages(
  pages: { items: ForSelectItem[] }[] | undefined,
): ForSelectItem[] {
  if (!pages) {
    return []
  }
  const seen = new Set<number>()
  const result: ForSelectItem[] = []
  for (const page of pages) {
    for (const item of page.items) {
      if (!seen.has(item.id)) {
        seen.add(item.id)
        result.push(item)
      }
    }
  }
  return result
}
