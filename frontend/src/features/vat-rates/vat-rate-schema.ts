import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the VAT rate create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `sources`). The shape mirrors the frozen backend contract 1:1.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('vatRates.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('vatRates.form.nameMax')),
    // Held nullable so the controlled number input can represent "empty";
    // the required-value superRefine below rejects a null at submit,
    // mirroring the backend's `required` rule. The wire value is normalized to
    // a real number in `api.ts` (Laravel's `decimal:2` cast serializes it as a
    // string), so a plain `z.number()` here keeps the RHF resolver types aligned.
    rate: z.number().nonnegative(t('vatRates.form.rateInvalid')).nullable(),
  }
}

function withRequiredValueRules<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as { rate: number | null }
    if (record.rate === null) {
      ctx.addIssue({ code: 'custom', path: ['rate'], message: t('vatRates.form.rateRequired') })
    }
  })
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateVatRateSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return withRequiredValueRules(
    z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) }),
    t,
  )
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateVatRateSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return buildCreateVatRateSchema(t, customFieldsSchema)
}

export type CreateVatRateFormValues = z.infer<ReturnType<typeof buildCreateVatRateSchema>>
export type UpdateVatRateFormValues = z.infer<ReturnType<typeof buildUpdateVatRateSchema>>
