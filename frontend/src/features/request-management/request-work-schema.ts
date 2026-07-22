import { z } from 'zod'
import type { TFunction } from 'i18next'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ApplicableAttribute } from '@/features/request-management/types'

/**
 * Client-side schema for the work panel's editable surface (spec 0049
 * AC-062/063): the working-state select and the dynamic `attribute_values`
 * map, one entry per `applicable_attributes` row, keyed by `code`. MIRRORS the
 * backend's `AttributeValueValidator` (per-type rule + `is_required`), it does
 * not replace it — the server stays authoritative (406/422 still applies).
 */

/** `enum` is checked against `attribute.options`; every other type gets its native shape. */
function buildAttributeScalarSchema(attribute: ApplicableAttribute, t: TFunction): z.ZodTypeAny {
  switch (attribute.type) {
    case 'integer':
    case 'decimal':
      return z.number().nullable()
    case 'boolean':
      return z.boolean()
    case 'enum': {
      const values = new Set(attribute.options.map((option) => option.value))
      return z
        .string()
        .nullable()
        .superRefine((value, ctx) => {
          if (value !== null && !values.has(value)) {
            ctx.addIssue({
              code: 'custom',
              message: t('requestManagement.workPanel.validation.enumInvalid', {
                defaultValue: 'Select a valid option.',
              }),
            })
          }
        })
    }
    case 'relation':
      return z.union([z.number(), z.array(z.number()), z.null()])
    // text/textarea + the string-backed scalars (date/datetime/time/email/url/color).
    default:
      return z.string().nullable()
  }
}

/** Builds the dynamic `attribute_values` shape, one key per applicable attribute `code`. */
function buildAttributeValuesSchema(attributes: ApplicableAttribute[], t: TFunction) {
  const shape: Record<string, z.ZodTypeAny> = {}
  for (const attribute of attributes) {
    shape[attribute.code] = buildAttributeScalarSchema(attribute, t)
  }

  const requiredCodes = attributes.filter((attribute) => attribute.is_required).map((attribute) => attribute.code)

  return z.object(shape).superRefine((values, ctx) => {
    for (const code of requiredCodes) {
      if (isEmptyCustomFieldValue((values as Record<string, unknown>)[code])) {
        ctx.addIssue({
          code: 'custom',
          path: [code],
          message: t('requestManagement.workPanel.validation.required', {
            defaultValue: 'This field is required.',
          }),
        })
      }
    }
  })
}

/**
 * `buildAttributeValuesSchema` derives its shape from a runtime-keyed
 * `Record<string, ZodTypeAny>`, so Zod infers it as `Record<string, unknown>`
 * — re-typed to the real value domain (mirrors `asCustomFieldsField`) so it
 * embeds cleanly under the form's `attribute_values` key.
 */
type TypedAttributeValuesSchema = z.ZodType<Record<string, CustomFieldValue>, Record<string, CustomFieldValue>>

export function buildRequestWorkSchema(attributes: ApplicableAttribute[], t: TFunction) {
  return z.object({
    opportunity_workflow_status_id: z.number().nullable(),
    attribute_values: buildAttributeValuesSchema(attributes, t) as unknown as TypedAttributeValuesSchema,
  })
}

export type RequestWorkFormValues = z.infer<ReturnType<typeof buildRequestWorkSchema>>
