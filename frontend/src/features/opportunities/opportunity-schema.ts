import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the opportunity create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0040 + amendment rev.1) 1:1:
 * `name`, `registry_id` required (D-4), every other relation nullable.
 */

/** Backend `name` column limit (`max:255`). */
export const NAME_MAX_LENGTH = 255

/** Backend limit on FILLED manager slots (`max:4`, `ValidatesManagerSlots`, mirrors registries). */
const MAX_MANAGERS = 4

/** Backend `estimated_value` column ceiling, `decimal(15,2)` (`max:9999999999999.99`). */
export const ESTIMATED_VALUE_MAX = 9999999999999.99

/** `success_probability` bounds (`unsignedTinyInteger`, BR-5: 0..100). */
export const SUCCESS_PROBABILITY_MIN = 0
export const SUCCESS_PROBABILITY_MAX = 100

/**
 * A required relation id: `null` (unset) fails the refine. See
 * `lead-schema.ts` for why the explicit `: boolean` return type on the
 * predicate is load-bearing (TS 5.5+'s automatic type-predicate inference
 * would otherwise narrow the field to non-nullable `number`, breaking every
 * consumer typed against the nullable form value).
 */
function requiredRelationId(message: string) {
  return z
    .number()
    .nullable()
    .refine((value): boolean => value !== null, { message })
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .trim()
      .min(1, t('opportunities.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('opportunities.form.nameMax')),
    // D-4: registry_id is the other required field (name is above).
    registry_id: requiredRelationId(t('opportunities.form.registryRequired')),
    // Spec 0043 D-3: the opportunity status is a mandatory FK, mirrors registry_id.
    opportunity_status_id: requiredRelationId(t('opportunities.form.opportunityStatusRequired')),
    referent_id: z.number().nullable(),
    commercial_id: z.number().nullable(),
    reporter_id: z.number().nullable(),
    supervisor_id: z.number().nullable(),
    source_id: z.number().nullable(),
    // Amendment rev.3 (AC-097/099): replaces the former single
    // `business_function_id`/`product_category_id` with an inline-editable
    // row collection (mirrors `manager_slots`: "Add" appends an EMPTY row).
    // Each id is individually nullable (a row starts empty and fills in
    // place), but `superRefine` below requires BOTH non-null per row before
    // submit — an incomplete row (including a freshly-added empty one)
    // blocks the form, surfaced as a single error on the collection. User
    // directive 2026-07-17: at least one row is REQUIRED (an empty collection
    // blocks submit), mirroring the backend `required|min:1`.
    product_lines: z
      .array(
        z.object({
          business_function_id: z.number().nullable(),
          product_category_id: z.number().nullable(),
        }),
      )
      .superRefine((rows, ctx) => {
        if (rows.length === 0) {
          ctx.addIssue({ code: z.ZodIssueCode.custom, message: t('opportunities.form.productLines.required') })
          return
        }
        const hasIncompleteRow = rows.some(
          (row) => row.business_function_id === null || row.product_category_id === null,
        )
        if (hasIncompleteRow) {
          ctx.addIssue({ code: z.ZodIssueCode.custom, message: t('opportunities.form.productLines.rowIncomplete') })
        }
      }),
    // Ordered, gap-aware "G.A. n" manager slots: index+1 = G.A. number, `null`
    // = an intentionally empty slot. At most MAX_MANAGERS filled.
    manager_slots: z
      .array(z.number().nullable())
      .refine(
        (slots) => slots.filter((slot) => slot !== null).length <= MAX_MANAGERS,
        t('opportunities.form.managersMax'),
      ),
    start_date: z.string().nullable(),
    expected_close_date: z.string().nullable(),
    estimated_value: z
      .number()
      .nonnegative(t('opportunities.form.estimatedValueInvalid'))
      .max(ESTIMATED_VALUE_MAX, t('opportunities.form.estimatedValueInvalid'))
      .nullable(),
    // Spec 0040 A-6: rendered as a 0..100 slider that always holds a value
    // (default 0), so this is a plain non-nullable integer — "0%" ≡ "not set".
    success_probability: z
      .number()
      .int()
      .min(SUCCESS_PROBABILITY_MIN, t('opportunities.form.successProbabilityInvalid'))
      .max(SUCCESS_PROBABILITY_MAX, t('opportunities.form.successProbabilityInvalid')),
  }
}

/** Create schema. */
export function buildCreateOpportunitySchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateOpportunitySchema(t: TFunction) {
  return buildCreateOpportunitySchema(t)
}

export type CreateOpportunityFormValues = z.infer<ReturnType<typeof buildCreateOpportunitySchema>>
export type UpdateOpportunityFormValues = CreateOpportunityFormValues
