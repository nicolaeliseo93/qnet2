import { z } from 'zod'
import type { TFunction } from 'i18next'
import { CUSTOM_FIELD_TYPES } from '@/features/custom-fields/types'

/**
 * Zod schema for the custom field DEFINITION admin form (spec 0021 AC-025),
 * built as a factory so validation messages are localized (same pattern as
 * `attribute-schema.ts`). The shape mirrors the frozen backend contract
 * (`StoreCustomFieldRequest`/`UpdateCustomFieldRequest`) 1:1: the
 * enum-requires-options and relation-requires-target cross-field rules are
 * enforced by `superRefine` below, mirroring the backend's own
 * `validateEnumOptions`/`validateRelationTarget`.
 */

/** Backend `key` column limit (`max:64`), snake_case identifier. */
const KEY_MAX_LENGTH = 64
/** Backend `label`/`options.*.value`/`options.*.label` column limit (`max:191`). */
const LABEL_MAX_LENGTH = 191
/** Backend `key` shape: `regex:/^[a-z0-9_]+$/`. */
const KEY_PATTERN = /^[a-z0-9_]+$/

function optionFields(t: TFunction) {
  return z.object({
    value: z
      .string()
      .min(1, t('customFields.form.optionValueRequired'))
      .max(LABEL_MAX_LENGTH, t('customFields.form.optionValueMax')),
    label: z
      .string()
      .min(1, t('customFields.form.optionLabelRequired'))
      .max(LABEL_MAX_LENGTH, t('customFields.form.optionLabelMax')),
    color: z.string(),
    icon: z.string(),
    is_default: z.boolean(),
  })
}

/** Loose, per-type-optional config bag (only the relevant subset is populated per `type`, see the payload builder). */
function configFields() {
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

/** Validation-builder bag (`required`/`unique`/`min`/`max`/`regex`/`email`/`url`/`exists`/`distinct`). */
function validationFields() {
  return z.object({
    required: z.boolean(),
    unique: z.boolean(),
    min: z.number().nullable(),
    max: z.number().nullable(),
    regex: z.string(),
    email: z.boolean(),
    url: z.boolean(),
    exists: z.boolean(),
    distinct: z.boolean(),
  })
}

function relationTargetFields() {
  return z.object({
    entity_type: z.string(),
    cardinality: z.enum(['one', 'many']),
    for_select_resource: z.string(),
  })
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    entity_type: z.string().min(1, t('customFields.form.entityTypeRequired')),
    key: z
      .string()
      .min(1, t('customFields.form.keyRequired'))
      .max(KEY_MAX_LENGTH, t('customFields.form.keyMax'))
      .regex(KEY_PATTERN, t('customFields.form.keyInvalid')),
    type: z.enum(CUSTOM_FIELD_TYPES),
    label: z
      .string()
      .min(1, t('customFields.form.labelRequired'))
      .max(LABEL_MAX_LENGTH, t('customFields.form.labelMax')),
    description: z.string(),
    help_text: z.string(),
    placeholder: z.string(),
    icon: z.string(),
    group: z.string(),
    tab: z.string(),
    sort_order: z.number().int(),
    is_indexed: z.boolean(),
    is_active: z.boolean(),
    config: configFields(),
    validation: validationFields(),
    relation_target: relationTargetFields(),
    options: z.array(optionFields(t)),
  }
}

/** Create schema. Edit reuses the exact same shape (spec: `sometimes` PATCH, diffed client-side). */
export function buildCreateCustomFieldDefinitionSchema(t: TFunction) {
  return z.object({ ...baseFields(t) }).superRefine((values, ctx) => {
    if (values.type === 'enum') {
      validateEnumOptions(values.options, t, ctx)
    }
    if (values.type === 'relation') {
      validateRelationTarget(values.relation_target, t, ctx)
    }
  })
}

function validateEnumOptions(
  options: { value: string }[],
  t: TFunction,
  ctx: z.RefinementCtx,
): void {
  if (options.length === 0) {
    ctx.addIssue({
      code: 'custom',
      path: ['options'],
      message: t('customFields.form.optionsRequiredForEnum'),
    })
    return
  }
  const seen = new Set<string>()
  for (const option of options) {
    if (seen.has(option.value)) {
      ctx.addIssue({
        code: 'custom',
        path: ['options'],
        message: t('customFields.form.optionValuesDuplicate'),
      })
      return
    }
    seen.add(option.value)
  }
}

function validateRelationTarget(
  target: { entity_type: string; for_select_resource: string },
  t: TFunction,
  ctx: z.RefinementCtx,
): void {
  if (!target.entity_type) {
    ctx.addIssue({
      code: 'custom',
      path: ['relation_target', 'entity_type'],
      message: t('customFields.form.relationEntityTypeRequired'),
    })
  }
  if (!target.for_select_resource) {
    ctx.addIssue({
      code: 'custom',
      path: ['relation_target', 'for_select_resource'],
      message: t('customFields.form.relationForSelectResourceRequired'),
    })
  }
}

export function buildUpdateCustomFieldDefinitionSchema(t: TFunction) {
  return buildCreateCustomFieldDefinitionSchema(t)
}

export type CustomFieldDefinitionFormValues = z.infer<
  ReturnType<typeof buildCreateCustomFieldDefinitionSchema>
>
