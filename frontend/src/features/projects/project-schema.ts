import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the project create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0025) 1:1. `code` is optional and
 * manual-entry-in-create-only: the create payload includes it when valued,
 * the update payload never includes it (spec 0025 PARTE A).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Backend `code` column limit (`string(32)`). */
const CODE_MAX_LENGTH = 32

function baseFields(t: TFunction) {
  return {
    // Manual code (spec 0025): trimmed, required (the create form auto-fills
    // the next sequential suggestion, editable), max 32. Read-only in edit
    // (enforced by the field-permission ceiling); the server still generates
    // one as a fallback when absent.
    code: z
      .string()
      .trim()
      .min(1, t('projects.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('projects.form.codeMax')),
    name: z
      .string()
      .min(1, t('projects.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('projects.form.nameMax')),
    description: z.string().nullable(),
    // Single-select relations (for-select standard): `null` = unset.
    registry_id: z.number().nullable(),
    // Held nullable so the controlled select can represent "unset"; the
    // required-value superRefine below rejects a null at submit, mirroring
    // the backend's `required` rule (spec D-5).
    pipeline_status_id: z.number().nullable(),
    source_id: z.number().nullable(),
    business_function_id: z.number().nullable(),
    // Geo cascade (spec 0027 BR-4): `country_id` required (withGeoHierarchyRule
    // below), the other three optional but parent-gated.
    country_id: z.number().nullable(),
    state_id: z.number().nullable(),
    province_id: z.number().nullable(),
    city_id: z.number().nullable(),
    product_category_id: z.number().nullable(),
    partner_id: z.number().nullable(),
    // Date inputs hold `''` for "empty" (never `null`); both required now
    // (BR-6 unchanged for ordering). Converted at the payload boundary.
    start_date: z.string().min(1, t('projects.form.startDateRequired')),
    end_date: z.string().min(1, t('projects.form.endDateRequired')),
    total_budget: z.number().nonnegative(t('projects.form.totalBudgetInvalid')).nullable(),
    target_lead: z.number().int().nonnegative(t('projects.form.targetLeadInvalid')).nullable(),
  }
}

/** BR-6: `end_date`, when set alongside `start_date`, must not be earlier. */
function withDateOrderRule<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as { start_date: string; end_date: string }
    if (record.start_date && record.end_date && record.end_date < record.start_date) {
      ctx.addIssue({
        code: 'custom',
        path: ['end_date'],
        message: t('projects.form.endDateBeforeStartDate'),
      })
    }
  })
}

/**
 * The single-select relations that must be set: `pipeline_status_id` (D-5),
 * plus `business_function_id` and `product_category_id` (now mandatory,
 * mirroring the backend's `required` rules). Held nullable in `baseFields` so
 * the controlled selects can represent "unset"; this rule rejects a null at
 * submit.
 */
const REQUIRED_RELATIONS = [
  { field: 'pipeline_status_id', message: 'projects.form.statusRequired' },
  { field: 'business_function_id', message: 'projects.form.businessFunctionRequired' },
  { field: 'product_category_id', message: 'projects.form.productCategoryRequired' },
] as const

function withRequiredRelationsRule<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as Record<(typeof REQUIRED_RELATIONS)[number]['field'], number | null>
    for (const { field, message } of REQUIRED_RELATIONS) {
      if (record[field] === null) {
        ctx.addIssue({ code: 'custom', path: [field], message: t(message) })
      }
    }
  })
}

/**
 * BR-4 (client-side part only): `country_id` is required, and a child level
 * may only be set when its parent is. The parent-BELONGS-TO-ancestor check
 * (e.g. state actually belongs to country) is server-side only — the client
 * has no such data (spec 0027 frontend note).
 */
function withGeoHierarchyRule<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
    }
    if (record.country_id === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['country_id'],
        message: t('projects.form.countryRequired'),
      })
    }
    if (record.province_id !== null && record.state_id === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['province_id'],
        message: t('projects.form.provinceRequiresState'),
      })
    }
    if (record.city_id !== null && record.state_id === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['city_id'],
        message: t('projects.form.cityRequiresState'),
      })
    }
  })
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateProjectSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  const object = z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
  return withGeoHierarchyRule(withRequiredRelationsRule(withDateOrderRule(object, t), t), t)
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateProjectSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return buildCreateProjectSchema(t, customFieldsSchema)
}

export type CreateProjectFormValues = z.infer<ReturnType<typeof buildCreateProjectSchema>>
export type UpdateProjectFormValues = CreateProjectFormValues
