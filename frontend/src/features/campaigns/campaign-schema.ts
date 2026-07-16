import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'
import { GEO_LEVELS } from '@/features/campaigns/campaign-geo'

/**
 * Zod schema for the campaign create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0025/0027) 1:1. `code` is
 * optional and manual-entry-in-create-only: the create payload includes it
 * when valued, the update payload never includes it (spec 0025 PARTE A).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Backend `code` column limit (`string(32)`). */
const CODE_MAX_LENGTH = 32

/**
 * The 2 classification fields whose requiredness flips with `project_id`
 * (BR-2). `state_id` LEFT this group (spec 0027 D-3): it is now one of the 4
 * geo fields, following BR-5 instead. `pipeline_status_id` LEFT this group
 * too (spec 0039 D-3): it is nullable/optional in every case now, the server
 * falling back to the system "Nuovo" status when omitted and standalone.
 */
const DERIVED_FIELDS = ['business_function_id', 'product_category_id'] as const

function baseFields(t: TFunction) {
  return {
    // Manual code (spec 0025): trimmed, required (the create form auto-fills
    // the next sequential suggestion, editable), max 32. Read-only in edit
    // (enforced by the field-permission ceiling); the server still generates
    // one as a fallback when absent.
    code: z
      .string()
      .trim()
      .min(1, t('campaigns.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('campaigns.form.codeMax')),
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
    pipeline_status_id: z.number().nullable(),
    business_function_id: z.number().nullable(),
    product_category_id: z.number().nullable(),
    // Geo cascade (spec 0027 BR-4/BR-5): EFFECTIVE values (own or inherited),
    // held nullable like every other controlled select. `country_id`'s
    // requiredness and the parent-before-child shape are enforced by
    // `withGeoHierarchyRule` below, aware of which levels are locked.
    country_id: z.number().nullable(),
    state_id: z.number().nullable(),
    province_id: z.number().nullable(),
    city_id: z.number().nullable(),
    // UI-only (never sent to the server): the geo levels currently owned by
    // the linked project, synced from `meta.geo` on selection (create) or
    // from `CampaignDetail.geo_locked_levels` (edit). Drives both
    // `<GeoSelect lockedLevels>` and this schema's hierarchy rule.
    geo_locked_levels: z.array(z.enum(GEO_LEVELS)),
    // Date inputs hold `''` for "empty" (never `null`); both required now
    // (dates are the campaign's own, never inherited from the project).
    start_date: z.string().min(1, t('campaigns.form.startDateRequired')),
    end_date: z.string().min(1, t('campaigns.form.endDateRequired')),
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
 * BR-2/AC-023/AC-043: the 2 classification fields are required only when the
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
      business_function_id: t('campaigns.form.businessFunctionRequired'),
      product_category_id: t('campaigns.form.productCategoryRequired'),
    }
    for (const field of DERIVED_FIELDS) {
      if (record[field] === null) {
        ctx.addIssue({ code: 'custom', path: [field], message: messages[field] })
      }
    }
  })
}

/**
 * BR-4 (client mirror) + BR-5 (spec 0027): `country_id` is required unless
 * the linked project already provides one (i.e. `'country'` is not one of
 * `geo_locked_levels`); a child level may only be set once its parent is
 * effectively present (own value OR inherited/locked) — `province`/`city`
 * both depend on `state`, not on each other (D-1). Full ancestry (state
 * belongs to country, etc.) is validated server-side only (BR-4 note).
 */
function withGeoHierarchyRule<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as {
      country_id: number | null
      state_id: number | null
      province_id: number | null
      city_id: number | null
      geo_locked_levels: (typeof GEO_LEVELS)[number][]
    }
    const locked = new Set(record.geo_locked_levels)
    const hasState = locked.has('state') || record.state_id !== null

    if (!locked.has('country') && record.country_id === null) {
      ctx.addIssue({ code: 'custom', path: ['country_id'], message: t('campaigns.form.countryRequired') })
    }
    if (record.province_id !== null && !hasState) {
      ctx.addIssue({
        code: 'custom',
        path: ['province_id'],
        message: t('campaigns.form.provinceRequiresState'),
      })
    }
    if (record.city_id !== null && !hasState) {
      ctx.addIssue({ code: 'custom', path: ['city_id'], message: t('campaigns.form.cityRequiresState') })
    }
  })
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateCampaignSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  const object = z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
  return withGeoHierarchyRule(withRequiredDerivedFieldsRule(withDateOrderRule(object, t), t), t)
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateCampaignSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return buildCreateCampaignSchema(t, customFieldsSchema)
}

export type CreateCampaignFormValues = z.infer<ReturnType<typeof buildCreateCampaignSchema>>
export type UpdateCampaignFormValues = CreateCampaignFormValues
