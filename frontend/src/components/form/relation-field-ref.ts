import type { ForSelectItem } from '@/features/for-select/types'
import type { RelationFieldRef } from '@/components/form/relation-select-field'

/**
 * Adapts a `ForSelectItem` hydration prop (`label` field) into the `{id, name}`
 * shape a relation field's `selected` prop expects. Kept out of
 * `relation-select-field.tsx` (react-refresh only allows a component file to
 * export components).
 */
export function toRelationFieldRef(item: ForSelectItem | null): RelationFieldRef | null {
  return item ? { id: item.id, name: item.label } : null
}

/** Array counterpart of {@link toRelationFieldRef}, for multi-relation hydration props. */
export function toRelationFieldRefs(items: ForSelectItem[]): RelationFieldRef[] {
  return items.map((item) => ({ id: item.id, name: item.label }))
}
