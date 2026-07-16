/**
 * Shared types for status reordering (spec 0039 D-4/D-5), consumed by both
 * `pipeline-statuses` and `lead-statuses` — the two modules that gained a
 * "system status" concept and a custom-only drag & drop reorder sheet.
 */

/** Marks a system-managed status row; `null` on an ordinary custom row. */
export type SystemStatusKey = 'new' | 'closed' | null

/** Embedded status group reference exposed by the status resources (spec 0039 D-6). */
export interface StatusGroupRef {
  id: number
  name: string
  color: string | null
}

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
