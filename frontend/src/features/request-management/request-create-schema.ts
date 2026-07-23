import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { ProductLineRow } from '@/features/product-lines/product-lines-field'

/**
 * D-3: `product_lines` is mandatory — at least one row — and every row
 * present must be complete (both ids chosen), mirroring the opportunity
 * form's own product-lines rule. The shared `ProductLinesField` enforces the
 * row shape (category disabled until a function is chosen); this only gates
 * the SET.
 */
function buildProductLinesSchema(t: TFunction) {
  return z.array(z.custom<ProductLineRow>()).superRefine((rows, ctx) => {
    if (rows.length === 0) {
      ctx.addIssue({
        code: 'custom',
        message: t('requestManagement.form.create.validation.productLinesRequired'),
      })
      return
    }
    rows.forEach((row, index) => {
      if (row.business_function_id === null || row.product_category_id === null) {
        ctx.addIssue({
          code: 'custom',
          path: [index],
          message: t('requestManagement.form.create.validation.productLineIncomplete'),
        })
      }
    })
  })
}

/**
 * Client-side schema for the request-creation form (spec 0057). Only
 * `registry_id`/`product_lines` are RHF-validated fields: the client identity/
 * contacts/address stay a plain buffered draft (mirrors the registries create
 * form, `use-registry-form.ts`'s `profileDraft`) since the reused
 * `PersonalDataCardForm`/`ContactsManager`/`AddressCreateField` components
 * validate themselves inline and are not RHF-connected — `useRequestCreateForm`
 * gates the submit on their own validity instead (D-2's mutually-exclusive
 * anagrafica branches).
 */
export function buildRequestCreateSchema(t: TFunction) {
  return z.object({
    registry_id: z.number().nullable(),
    product_lines: buildProductLinesSchema(t),
  })
}

export type RequestCreateFormValues = z.infer<ReturnType<typeof buildRequestCreateSchema>>
