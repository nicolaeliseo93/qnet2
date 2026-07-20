/**
 * Shared types for status reordering (spec 0039 D-4/D-5), consumed by
 * `pipeline-statuses`, `lead-statuses` and `opportunity-statuses` (spec
 * 0043) — the modules that gained a "system status" concept and a
 * custom-only drag & drop reorder sheet.
 */

/**
 * Marks a system-managed status row; `null` on an ordinary custom row.
 * `lost` is opportunity-statuses only (spec 0043 D-2): "Persa", closed, the
 * fixed last row of the tail.
 */
export type SystemStatusKey = 'new' | 'won' | 'lost' | 'discarded' | 'closed' | null

/** Fixed enum of status groups, replacing the former "status groups" lookup module. */
export const STATUS_GROUPS = ['open', 'pending', 'closed'] as const

/** One of the three fixed status group values. */
export type StatusGroupValue = (typeof STATUS_GROUPS)[number]

/** One row as reordered in the sheet: id, display name and its pin state. */
export interface StatusReorderItem {
  id: number
  name: string
  systemKey: SystemStatusKey
}

/** A single entry of the fresh, full list returned by `POST /{resource}/reorder`. */
export interface ReorderedStatusEntry {
  id: number
  sort_order: number
  system_key: SystemStatusKey
}
