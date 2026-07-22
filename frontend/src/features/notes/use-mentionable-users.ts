import { useInfiniteQuery } from '@tanstack/react-query'
import { fetchMentionableUsers, NOTES_MENTIONABLE_PAGE_SIZE } from '@/features/notes/api'
import { notesKeys } from '@/features/notes/query-keys'

interface UseMentionableUsersOptions {
  entityType: string
  entityId: number
  /** Debounced search term typed after `@` in the mention picker. */
  search: string
  /** Gates the query so it only runs once the picker is open. */
  enabled?: boolean
}

/**
 * Contextual mention lookup of the entity currently open (D-10): only users
 * who can read that specific record, resolved server-side. The response
 * shape is deliberately identical to the for-select contract
 * (`PaginatedResponse<ForSelectItem>`, ADR 0011) — that's why the offset
 * pagination below mirrors `useForSelect` (`features/for-select/use-for-select.ts`)
 * exactly. `useForSelect` itself is NOT reusable as-is here: its fetcher
 * hardcodes the `/${resource}/for-select` URL, while this feature calls
 * `/notes/mentionable-users`. Callers can still reuse `flattenForSelectPages`
 * from `features/for-select/use-for-select.ts` to flatten this hook's pages —
 * it operates on the same `{ items: ForSelectItem[] }` page shape.
 */
export function useMentionableUsers({
  entityType,
  entityId,
  search,
  enabled = true,
}: UseMentionableUsersOptions) {
  return useInfiniteQuery({
    queryKey: notesKeys.mentionable(entityType, entityId, search),
    queryFn: ({ pageParam }) =>
      fetchMentionableUsers({
        entityType,
        entityId,
        search,
        offset: pageParam,
        limit: NOTES_MENTIONABLE_PAGE_SIZE,
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
