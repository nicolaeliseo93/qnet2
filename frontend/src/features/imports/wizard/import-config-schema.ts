import type { ImportGlobalFieldDescriptor } from '@/features/imports/wizard/types'

/**
 * Every global config field is held as `number | null` (a relation id, or
 * unset) regardless of whether it is required: keeping the value type uniform
 * lets the form start from `null` defaults, with "required" enforced by the
 * mapping schema's refinement (the config controls live inside the mapping
 * step's form — see `import-step-mapping.tsx`).
 */
export type ImportConfigFormValues = Record<string, number | null>

/**
 * Fills every known field id with an explicit `null`, so an incomplete caller
 * value never leaves a key `undefined` in the form's `global_config` object.
 */
export function withConfigDefaults(
  globalFields: ImportGlobalFieldDescriptor[],
  values: ImportConfigFormValues,
): ImportConfigFormValues {
  const filled: ImportConfigFormValues = {}
  for (const field of globalFields) {
    filled[field.id] = values[field.id] ?? null
  }
  return filled
}
