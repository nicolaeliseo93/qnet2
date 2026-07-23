/**
 * Row-scope resolution for the `multiselect` cell editor (user directive
 * 2026-07-23). Its own module so the pure function stays directly unit-testable
 * and the editor file keeps exporting only its component (fast-refresh rule).
 */
import type { TableRow } from '@/features/table/types'

/**
 * Resolves `scope` against the edited row. A scope column holds a relation
 * projection (`{id, name}`), a bare id, or an ARRAY of either; anything else
 * contributes no param, so a row missing the value degrades to the unfiltered
 * list rather than sending a junk filter.
 */
export function resolveMultiScopeParams(
  scope: Record<string, string> | undefined,
  row: TableRow | undefined,
): Record<string, number[]> | undefined {
  if (!scope || !row) {
    return undefined
  }

  const params: Record<string, number[]> = {}

  for (const [param, columnId] of Object.entries(scope)) {
    const cell = row[columnId]
    const ids = (Array.isArray(cell) ? cell : [cell])
      .map((entry) =>
        typeof entry === 'number'
          ? entry
          : typeof (entry as { id?: unknown } | null)?.id === 'number'
            ? (entry as { id: number }).id
            : null,
      )
      .filter((id): id is number => id !== null)

    if (ids.length > 0) {
      params[param] = ids
    }
  }

  return Object.keys(params).length > 0 ? params : undefined
}
