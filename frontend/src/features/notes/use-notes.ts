import { useInfiniteQuery } from '@tanstack/react-query'
import { fetchNotes, NOTES_DEFAULT_PAGE_SIZE } from '@/features/notes/api'
import { notesKeys } from '@/features/notes/query-keys'

/**
 * Infinite-scroll feed of a host record's root notes (spec 0052, D-13),
 * mirroring `useActivityLog`'s keyset pattern. The next page param is the
 * opaque cursor the backend returns as `meta.next_cursor`; `getNextPageParam`
 * returns `undefined` once `meta.has_more` is false, which is what sets
 * TanStack Query's `hasNextPage` to `false`.
 */
export function useNotes(entityType: string, entityId: number, enabled = true) {
  return useInfiniteQuery({
    queryKey: notesKeys.list(entityType, entityId),
    queryFn: ({ pageParam }) =>
      fetchNotes({ entityType, entityId, cursor: pageParam, limit: NOTES_DEFAULT_PAGE_SIZE }),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => (lastPage.meta.has_more ? (lastPage.meta.next_cursor ?? undefined) : undefined),
    enabled,
  })
}
