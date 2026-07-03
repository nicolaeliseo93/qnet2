import { useQuery } from '@tanstack/react-query'
import { fetchFieldCatalogue } from '@/features/roles/field-catalogue-api'

/** Field catalogue rarely changes within a session (5 min, mirrors `use-resource-meta`). */
const FIELD_CATALOGUE_STALE_TIME_MS = 5 * 60 * 1000

const FIELD_CATALOGUE_QUERY_KEY = ['authorization', 'fields'] as const

/**
 * Loads the field catalogue backing the Role form's field-permission matrix
 * (spec 0006). `enabled` lets the caller defer the fetch until the matrix
 * section will actually render (the actor can write the role at all).
 */
export function useFieldCatalogue(enabled = true) {
  return useQuery({
    queryKey: FIELD_CATALOGUE_QUERY_KEY,
    queryFn: fetchFieldCatalogue,
    staleTime: FIELD_CATALOGUE_STALE_TIME_MS,
    enabled,
  })
}
