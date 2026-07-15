/**
 * Pure conversions between the API's `extra_fields` record shape
 * (`Record<string, string> | null`, spec 0033) and the ordered key/value
 * rows the form's field array edits. Kept out of `extra-fields-editor.tsx`
 * so that file stays component-only (`react-refresh/only-export-components`).
 */

/** A single free-form key/value pair as edited in the form (spec 0033, AC-014). */
export interface ExtraFieldEntry {
  key: string
  value: string
}

/** Converts the persisted `extra_fields` record into the ordered rows the field array edits. */
export function recordToEntries(record: Record<string, string> | null | undefined): ExtraFieldEntry[] {
  if (!record) return []
  return Object.entries(record).map(([key, value]) => ({ key, value }))
}

/**
 * Converts the field array back into the `extra_fields` record persisted by
 * the API. Blank keys are dropped defensively (the schema already rejects
 * them before submit); an empty result maps to `null` (spec 0033 data
 * contract: "manda l'oggetto, o null se vuoto").
 */
export function entriesToRecord(entries: ExtraFieldEntry[]): Record<string, string> | null {
  const record: Record<string, string> = {}
  for (const entry of entries) {
    const key = entry.key.trim()
    if (!key) continue
    record[key] = entry.value
  }
  return Object.keys(record).length > 0 ? record : null
}
