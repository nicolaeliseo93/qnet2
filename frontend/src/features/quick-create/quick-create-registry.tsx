import type { QuickCreateEntry } from '@/features/quick-create/types'
import { moduleEntries } from '@/features/quick-create/quick-create-entries/module-entries'
import { hierarchicalEntries } from '@/features/quick-create/quick-create-entries/hierarchical-entries'
import { advancedEntries } from '@/features/quick-create/quick-create-entries/advanced-entries'

/**
 * resource (for-select segment, e.g. `SOURCES_FOR_SELECT_RESOURCE`) -> quick
 * create wiring, for every relation that targets an async module (spec 0028,
 * decision D3). The geo resources and other static/tree `SearchableSelect`s
 * are deliberately absent — `resolveQuickCreate` returns `null` for them, and
 * `QuickCreateButton` renders nothing.
 */
const registry: Record<string, QuickCreateEntry> = {
  ...moduleEntries,
  ...hierarchicalEntries,
  ...advancedEntries,
}

/** Looks up a resource's quick-create entry; `null` when the resource is out of scope (AC-011). */
export function resolveQuickCreate(resource: string): QuickCreateEntry | null {
  return registry[resource] ?? null
}
