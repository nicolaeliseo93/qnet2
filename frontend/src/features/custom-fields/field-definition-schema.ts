import { z } from 'zod'
import { CUSTOM_FIELD_TYPES } from '@/features/custom-fields/types'

/**
 * Zod building blocks shared by every "field definition" form's schema
 * (`custom-field-definition-schema.ts`, `attribute-schema.ts`): the `type`
 * picker, the per-type `config` bag, the ENUM `options` rows and the RELATION
 * `relation_target`, plus the two cross-field rules that gate them
 * (enum-requires-options, relation-requires-target — mirrors the backend's
 * `ValidatesFieldTypeDefinition` concern). Each concrete schema supplies its
 * own localized {@link FieldDefinitionSchemaMessages} so error copy stays
 * under the caller's own i18n namespace (`customFields.form.*` vs
 * `attributes.form.*`) while the validation LOGIC is written once.
 */

/** Localized messages the shared cross-field rules attach to their `ctx.addIssue` calls. */
export interface FieldDefinitionSchemaMessages {
  optionValueRequired: string
  optionValueMax: string
  optionLabelRequired: string
  optionLabelMax: string
  optionsRequiredForEnum: string
  optionValuesDuplicate: string
  relationEntityTypeRequired: string
  relationForSelectResourceRequired: string
}

export const fieldDefinitionTypeSchema = z.enum(CUSTOM_FIELD_TYPES)

export function fieldDefinitionOptionFields(messages: FieldDefinitionSchemaMessages, maxLength: number) {
  return z.object({
    value: z.string().min(1, messages.optionValueRequired).max(maxLength, messages.optionValueMax),
    label: z.string().min(1, messages.optionLabelRequired).max(maxLength, messages.optionLabelMax),
    color: z.string(),
    icon: z.string(),
    is_default: z.boolean(),
  })
}

export function fieldDefinitionConfigFields() {
  return z.object({
    minLength: z.number().int().nonnegative().nullable(),
    maxLength: z.number().int().nonnegative().nullable(),
    regex: z.string(),
    transform: z.enum(['', 'upper', 'lower', 'capitalize']),
    rows: z.number().int().positive().nullable(),
    min: z.number().nullable(),
    max: z.number().nullable(),
    step: z.number().positive().nullable(),
    decimals: z.number().int().nonnegative().nullable(),
    display: z.string(),
  })
}

export function fieldDefinitionRelationTargetFields() {
  return z.object({
    entity_type: z.string(),
    cardinality: z.enum(['one', 'many']),
    for_select_resource: z.string(),
  })
}

/** `type === 'enum'` requires at least one option, with unique `value`s (spec AC-003/AC-018). */
export function validateEnumOptions(
  options: { value: string }[],
  messages: FieldDefinitionSchemaMessages,
  ctx: z.RefinementCtx,
): void {
  if (options.length === 0) {
    ctx.addIssue({ code: 'custom', path: ['options'], message: messages.optionsRequiredForEnum })
    return
  }
  const seen = new Set<string>()
  for (const option of options) {
    if (seen.has(option.value)) {
      ctx.addIssue({ code: 'custom', path: ['options'], message: messages.optionValuesDuplicate })
      return
    }
    seen.add(option.value)
  }
}

/** `type === 'relation'` requires a valid `entity_type` + `for_select_resource` (spec AC-018). */
export function validateRelationTarget(
  target: { entity_type: string; for_select_resource: string },
  messages: FieldDefinitionSchemaMessages,
  ctx: z.RefinementCtx,
): void {
  if (!target.entity_type) {
    ctx.addIssue({
      code: 'custom',
      path: ['relation_target', 'entity_type'],
      message: messages.relationEntityTypeRequired,
    })
  }
  if (!target.for_select_resource) {
    ctx.addIssue({
      code: 'custom',
      path: ['relation_target', 'for_select_resource'],
      message: messages.relationForSelectResourceRequired,
    })
  }
}
