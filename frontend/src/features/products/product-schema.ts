import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the product create/edit form's GENERIC fields, built as a
 * factory so validation messages are localized via the i18n `t` function. The
 * dynamic attribute values are intentionally typed loosely here
 * (`attributes`): their real validation (required/type/ENUM membership) is
 * server-side, driven by the selected category's effective attributes (spec
 * AC-015) — duplicating that logic client-side would drift from the backend
 * the moment a category's assignments change.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('products.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('products.form.nameMax')),
    description: z.string().nullable(),
    // cost/price/category_id are held nullable so the controlled inputs can
    // represent "empty"; the required-value superRefine below rejects a null
    // at submit, mirroring the backend's `required` rules.
    cost: z.number().nonnegative(t('products.form.costInvalid')).nullable(),
    price: z.number().nonnegative(t('products.form.priceInvalid')).nullable(),
    category_id: z.number().nullable(),
    product_type: z.enum(['SERVICE']),
    attributes: z.record(z.string(), z.union([z.string(), z.number(), z.boolean(), z.null()])),
  }
}

function withRequiredValueRules<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as { category_id: number | null; cost: number | null; price: number | null }
    if (record.category_id === null) {
      ctx.addIssue({ code: 'custom', path: ['category_id'], message: t('products.form.categoryRequired') })
    }
    if (record.cost === null) {
      ctx.addIssue({ code: 'custom', path: ['cost'], message: t('products.form.costRequired') })
    }
    if (record.price === null) {
      ctx.addIssue({ code: 'custom', path: ['price'], message: t('products.form.priceRequired') })
    }
  })
}

export function buildCreateProductSchema(t: TFunction) {
  return withRequiredValueRules(z.object({ ...baseFields(t) }), t)
}

export function buildUpdateProductSchema(t: TFunction) {
  return buildCreateProductSchema(t)
}

export type CreateProductFormValues = z.infer<ReturnType<typeof buildCreateProductSchema>>
export type UpdateProductFormValues = CreateProductFormValues
