import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Resolves each selected global-config value (campaign/project/source/status)
 * into a display label + value: the field's own catalog label
 * (`run.global_fields`) pairs with the raw configured value (an id) — the
 * frozen `summary` contract only exposes `global_config` as raw values, so
 * there is no resolved for-select label to show, and this falls back to the
 * value itself (spec 0033 AC-024: "risolvi le label se disponibili o mostra i
 * valori"). Shared by the wizard's own summary step and the spec 0034
 * read-only detail page, so the two never drift on how they read the same
 * contract field.
 */
export function resolveGlobalConfigEntries(
  run: ImportRunDetail | null,
  globalConfig: Record<string, unknown>,
  translate: (key: string) => string,
): Array<{ label: string; value: string }> {
  if (!run) return []
  const entries: Array<{ label: string; value: string }> = []
  for (const field of run.global_fields) {
    const value = globalConfig[field.id]
    if (value === null || value === undefined || value === '') continue
    entries.push({ label: translate(field.label), value: String(value) })
  }
  return entries
}

/**
 * Resolves a mapped target field id to its catalog label (a backend
 * default-namespace i18n key), falling back to the id when the run's field
 * catalog is unavailable.
 */
export function resolveFieldLabel(
  run: ImportRunDetail | null,
  fieldId: string,
  translate: (key: string) => string,
): string {
  const field = run?.fields.find((candidate) => candidate.id === fieldId)
  return field ? translate(field.label) : fieldId
}
