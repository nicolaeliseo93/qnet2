import { z } from 'zod'
import type { TFunction } from 'i18next'
import { ATTRIBUTE_DATA_TYPES } from '@/features/attributes/types'

/**
 * Zod schema for the attribute create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `businessFunctions`/`referentTypes`). The shape mirrors the frozen
 * backend contract (spec 0017) 1:1: the ENUM-requires-options and
 * unique-option-values rules are cross-field, enforced by `superRefine`
 * below (spec AC-003).
 */

/** Backend `code` column limit (`max:64`). */
const CODE_MAX_LENGTH = 64
/** Backend `name`/option `label`/`value` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191
/** Backend `code` shape: snake_case identifier (spec engineering.md §1.2). */
const CODE_PATTERN = /^[a-z][a-z0-9_]*$/

function optionFields(t: TFunction) {
  return z.object({
    value: z
      .string()
      .min(1, t('attributes.form.optionValueRequired'))
      .max(NAME_MAX_LENGTH, t('attributes.form.optionValueMax')),
    label: z
      .string()
      .min(1, t('attributes.form.optionLabelRequired'))
      .max(NAME_MAX_LENGTH, t('attributes.form.optionLabelMax')),
  })
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    code: z
      .string()
      .min(1, t('attributes.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('attributes.form.codeMax'))
      .regex(CODE_PATTERN, t('attributes.form.codeInvalid')),
    name: z
      .string()
      .min(1, t('attributes.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('attributes.form.nameMax')),
    data_type: z.enum(ATTRIBUTE_DATA_TYPES),
    options: z.array(optionFields(t)),
  }
}

/** Create schema. Edit reuses the exact same shape (spec: full-replace PATCH). */
export function buildCreateAttributeSchema(t: TFunction) {
  return z.object({ ...baseFields(t) }).superRefine((values, ctx) => {
    if (values.data_type === 'ENUM' && values.options.length === 0) {
      ctx.addIssue({
        code: 'custom',
        path: ['options'],
        message: t('attributes.form.optionsRequiredForEnum'),
      })
      return
    }
    const seen = new Set<string>()
    for (const option of values.options) {
      if (seen.has(option.value)) {
        ctx.addIssue({
          code: 'custom',
          path: ['options'],
          message: t('attributes.form.optionValuesDuplicate'),
        })
        return
      }
      seen.add(option.value)
    }
  })
}

export function buildUpdateAttributeSchema(t: TFunction) {
  return buildCreateAttributeSchema(t)
}

export type CreateAttributeFormValues = z.infer<ReturnType<typeof buildCreateAttributeSchema>>
export type UpdateAttributeFormValues = CreateAttributeFormValues
