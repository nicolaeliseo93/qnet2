import { z } from 'zod'
import type { TFunction } from 'i18next'
import type {
  ImportFieldDescriptor,
  ImportGlobalFieldDescriptor,
} from '@/features/imports/wizard/types'

/**
 * The mapping step's form values: column name -> target (field id | sentinel),
 * the dedup strategy, and the global configuration values (campaign/source/
 * operator/status) — the configuration controls now live inside this step, so
 * a single submit persists mapping + config + dedup together.
 */
export interface ImportMappingFormValues {
  mapping: Record<string, string>
  dedup_strategy: string
  global_config: Record<string, number | null>
}

/**
 * Zod schema for the mapping step. Neither `mapping` nor `global_config` has a
 * per-key shape (both are data-driven), so required-field coverage — a required
 * mappable field must have a target, a required global field must be set — is
 * enforced by a top-level `superRefine`, mirroring `projects/project-schema.ts`.
 */
export function buildImportMappingSchema(
  fields: ImportFieldDescriptor[],
  globalFields: ImportGlobalFieldDescriptor[],
  t: TFunction,
) {
  return z
    .object({
      mapping: z.record(z.string(), z.string()),
      dedup_strategy: z.string().min(1, t('mapping.errors.dedupRequired')),
      global_config: z.record(z.string(), z.number().nullable()),
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

      for (const field of globalFields) {
        if (field.required && values.global_config[field.id] == null) {
          ctx.addIssue({
            code: 'custom',
            path: ['global_config', field.id],
            message: t('config.errors.required'),
          })
        }
      }
    })
}
