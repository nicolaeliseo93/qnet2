import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { ImportFieldDescriptor } from '@/features/imports/wizard/types'

/** The mapping step's form values: column name -> target (field id | sentinel), plus the dedup strategy. */
export interface ImportMappingFormValues {
  mapping: Record<string, string>
  dedup_strategy: string
}

/**
 * Zod schema for the mapping step. `mapping` itself has no per-key shape
 * (columns are data-driven), so required-field coverage is enforced by a
 * top-level `superRefine` rather than by the field schema, mirroring
 * `projects/project-schema.ts`'s `withRequiredStatusRule` pattern.
 */
export function buildImportMappingSchema(fields: ImportFieldDescriptor[], t: TFunction) {
  return z
    .object({
      mapping: z.record(z.string(), z.string()),
      dedup_strategy: z.string().min(1, t('mapping.errors.dedupRequired')),
    })
    .superRefine((values, ctx) => {
      const mappedFieldIds = new Set(Object.values(values.mapping))
      const missing = fields.filter((field) => field.required && !mappedFieldIds.has(field.id))
      if (missing.length > 0) {
        ctx.addIssue({
          code: 'custom',
          path: ['mapping'],
          message: t('mapping.badges.requiredMissing', {
            fields: missing.map((field) => field.label).join(', '),
          }),
        })
      }
    })
}
