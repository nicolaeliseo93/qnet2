import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { ImportGlobalFieldDescriptor } from '@/features/imports/wizard/types'

/**
 * Every global config field is held as `number | null` (a relation id, or
 * unset) regardless of whether it is required: keeping the schema's input
 * and output types identical lets the form start from `null` defaults, with
 * "required" enforced by the `superRefine` below rather than by the field's
 * own type — mirrors `projects/project-schema.ts`'s `withRequiredStatusRule`.
 */
export type ImportConfigFormValues = Record<string, number | null>

/**
 * Zod schema for the wizard's global-configuration step, built from the
 * run's `global_fields` catalog. `z.record` (rather than `z.object` with a
 * dynamically-built shape) is what keeps the inferred type exactly
 * `Record<string, number | null>` for every field id, known only at
 * runtime.
 */
export function buildImportConfigSchema(fields: ImportGlobalFieldDescriptor[], t: TFunction) {
  return z.record(z.string(), z.number().nullable()).superRefine((values, ctx) => {
    for (const field of fields) {
      if (field.required && values[field.id] == null) {
        ctx.addIssue({ code: 'custom', path: [field.id], message: t('config.errors.required') })
      }
    }
  })
}
