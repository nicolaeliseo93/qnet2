import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the campaign create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0023) 1:1. `code` is intentionally
 * absent (BR-1, AC-046): it is never part of the form values or the payload.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** The 4 classification fields whose requiredness flips with `project_id` (BR-2). */
const DERIVED_FIELDS = [
  'project_status_id',
  'business_function_id',
  'state_id',
  'product_category_id',
] as const

function baseFields(t: TFunction) {
  return {
    // `null` = unset/standalone (for-select standard).
    project_id: z.number().nullable(),
    name: z
      .string()
      .min(1, t('campaigns.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('campaigns.form.nameMax')),
    description: z.string().nullable(),
    // Always the campaign's own, editable relation (AC-042 prefills but never locks these).
    registry_id: z.number().nullable(),
    source_id: z.number().nullable(),
    partner_id: z.number().nullable(),
    // Derived (BR-2): required when standalone, forced null/read-only when linked;
    // held nullable so the controlled selects can represent "unset" — the
    // required-when-standalone superRefine below mirrors the backend's rule.
    project_status_id: z.number().nullable(),
    business_function_id: z.number().nullable(),
    state_id: z.number().nullable(),
    product_category_id: z.number().nullable(),
    // Date inputs hold `''` for "empty" (never `null`), converted to `null` at
    // the payload boundary (mirrors the `projects` dates convention).
    start_date: z.string(),
    end_date: z.string(),
    total_budget: z.number().nonnegative(t('campaigns.form.totalBudgetInvalid')).nullable(),
    target_lead: z.number().int().nonnegative(t('campaigns.form.targetLeadInvalid')).nullable(),
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
        message: t('campaigns.form.endDateBeforeStartDate'),
      })
    }
  })
}

/**
 * BR-2/AC-023/AC-043: the 4 classification fields are required only when the
 * campaign is standalone (`project_id === null`). When linked, the backend
 * forces/derives them and the payload builder never sends them regardless of
 * their form value, so no "must be empty" counterpart is needed here.
 */
function withRequiredDerivedFieldsRule<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as { project_id: number | null } & Record<
      (typeof DERIVED_FIELDS)[number],
      number | null
    >
    if (record.project_id !== null) {
      return
    }
    const messages: Record<(typeof DERIVED_FIELDS)[number], string> = {
      project_status_id: t('campaigns.form.statusRequired'),
      business_function_id: t('campaigns.form.businessFunctionRequired'),
      state_id: t('campaigns.form.stateRequired'),
      product_category_id: t('campaigns.form.productCategoryRequired'),
    }
    for (const field of DERIVED_FIELDS) {
      if (record[field] === null) {
        ctx.addIssue({ code: 'custom', path: [field], message: messages[field] })
      }
    }
  })
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateCampaignSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  const object = z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
  return withRequiredDerivedFieldsRule(withDateOrderRule(object, t), t)
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateCampaignSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return buildCreateCampaignSchema(t, customFieldsSchema)
}

export type CreateCampaignFormValues = z.infer<ReturnType<typeof buildCreateCampaignSchema>>
export type UpdateCampaignFormValues = CreateCampaignFormValues
