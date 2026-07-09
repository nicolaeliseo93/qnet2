import { useQuery } from '@tanstack/react-query'
import { fetchCustomFieldEntities } from '@/features/custom-fields/api'

/** Custom-fieldable modules change only on deploy: cache for the whole session (mirrors `useResourceMeta`'s rationale). */
const CUSTOM_FIELD_ENTITIES_STALE_TIME_MS = 5 * 60 * 1000

/**
 * Loads the custom-fieldable module catalogue (`GET /custom-fields/entities`)
 * feeding the admin form's `entity_type` and `relation_target.entity_type`
 * pickers.
 */
export function useCustomFieldEntities() {
  return useQuery({
    queryKey: ['custom-fields', 'entities'],
    queryFn: fetchCustomFieldEntities,
    staleTime: CUSTOM_FIELD_ENTITIES_STALE_TIME_MS,
  })
}
