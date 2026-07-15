import { EXTRA_TARGET, IGNORE_TARGET } from '@/features/imports/wizard/types'
import type { DetectedColumn, ImportFieldDescriptor } from '@/features/imports/wizard/types'

/**
 * Mapping signals for the mapping step (AC-022): which required fields are
 * still unmapped, which columns are flagged duplicate by the analysis, and
 * which fields are targeted by more than one column (a conflict — only the
 * last-written value would survive server-side). Pure function so it is unit
 * testable without mounting the step component.
 */
export interface MappingSignals {
  /** Field ids required by the definition but not targeted by any column. */
  requiredMissing: string[]
  /** Field ids targeted by two or more columns. */
  conflictFieldIds: Set<string>
}

export function computeMappingSignals(
  columns: DetectedColumn[],
  fields: ImportFieldDescriptor[],
  mapping: Record<string, string>,
): MappingSignals {
  const targetCounts = new Map<string, number>()

  for (const column of columns) {
    const target = mapping[column.key]
    if (target && target !== IGNORE_TARGET && target !== EXTRA_TARGET) {
      targetCounts.set(target, (targetCounts.get(target) ?? 0) + 1)
    }
  }

  const conflictFieldIds = new Set(
    [...targetCounts.entries()].filter(([, count]) => count > 1).map(([fieldId]) => fieldId),
  )

  const requiredMissing = fields
    .filter((field) => field.required && (targetCounts.get(field.id) ?? 0) === 0)
    .map((field) => field.id)

  return { requiredMissing, conflictFieldIds }
}
