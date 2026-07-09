import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the tag create/edit form, built as a factory so validation
 * messages are localized via the i18n `t` function (same pattern as
 * `referentTypes`/`sources`). The shape mirrors the frozen backend contract
 * (spec 0019) 1:1.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('tags.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('tags.form.nameMax')),
  }
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateTagSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateTagSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateTagFormValues = z.infer<ReturnType<typeof buildCreateTagSchema>>
export type UpdateTagFormValues = z.infer<ReturnType<typeof buildUpdateTagSchema>>
