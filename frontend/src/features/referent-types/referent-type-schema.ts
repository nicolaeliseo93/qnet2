import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the referent-type create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `businessFunctions`). The shape mirrors the frozen backend contract
 * (spec 0016) 1:1.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('referentTypes.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('referentTypes.form.nameMax')),
  }
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateReferentTypeSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateReferentTypeSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateReferentTypeFormValues = z.infer<
  ReturnType<typeof buildCreateReferentTypeSchema>
>
export type UpdateReferentTypeFormValues = z.infer<
  ReturnType<typeof buildUpdateReferentTypeSchema>
>
