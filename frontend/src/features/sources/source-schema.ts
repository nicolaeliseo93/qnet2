import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the source create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `referentTypes`). The shape mirrors the frozen backend contract
 * (spec 0018) 1:1.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('sources.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('sources.form.nameMax')),
  }
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateSourceSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateSourceSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateSourceFormValues = z.infer<ReturnType<typeof buildCreateSourceSchema>>
export type UpdateSourceFormValues = z.infer<ReturnType<typeof buildUpdateSourceSchema>>
