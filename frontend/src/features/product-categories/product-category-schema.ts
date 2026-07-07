import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the product-category create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0017) 1:1; the anti-cycle
 * `parent_id` rule is enforced server-side (the picker also excludes the
 * category's own subtree client-side as a UX affordance, not a validity gate).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('productCategories.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('productCategories.form.nameMax')),
    parent_id: z.number().nullable(),
    description: z.string().nullable(),
    attributes: z.array(
      z.object({
        attribute_id: z.number(),
        is_required: z.boolean(),
        sort_order: z.number().int(),
      }),
    ),
  }
}

export function buildCreateProductCategorySchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export function buildUpdateProductCategorySchema(t: TFunction) {
  return z.object({ ...baseFields(t) })
}

export type CreateProductCategoryFormValues = z.infer<
  ReturnType<typeof buildCreateProductCategorySchema>
>
export type UpdateProductCategoryFormValues = CreateProductCategoryFormValues
