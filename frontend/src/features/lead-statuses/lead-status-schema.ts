import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the lead status create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `pipeline-statuses`). The shape mirrors the frozen backend contract
 * (spec 0029) 1:1. `color` stores a palette TOKEN (empty string = unset,
 * mapped to `null` by the payload builder) — see `ColorTokenPicker`.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Backend `color` column limit (`max:32`). */
const COLOR_MAX_LENGTH = 32

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('leadStatuses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('leadStatuses.form.nameMax')),
    color: z.string().max(COLOR_MAX_LENGTH, t('leadStatuses.form.colorMax')),
    sort_order: z.coerce
      .number()
      .int(t('leadStatuses.form.sortOrderInvalid'))
      .min(0, t('leadStatuses.form.sortOrderMin')),
  }
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateLeadStatusSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateLeadStatusSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateLeadStatusFormValues = z.infer<ReturnType<typeof buildCreateLeadStatusSchema>>
export type UpdateLeadStatusFormValues = z.infer<ReturnType<typeof buildUpdateLeadStatusSchema>>
