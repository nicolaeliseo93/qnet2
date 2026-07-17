import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createProduct, updateProduct } from '@/features/products/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/products/product-form-payload'
import {
  buildCreateProductSchema,
  buildUpdateProductSchema,
  type CreateProductFormValues,
} from '@/features/products/product-schema'
import type { ProductDetail, ProductFormMode } from '@/features/products/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'

/** Server-side generic field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'cost',
  'price',
  'category_id',
  'product_type',
  'vat_rate_id',
  'supplier_id',
] as const

/** Default product type for a new product (SERVICE-only catalogue for now). */
const DEFAULT_PRODUCT_TYPE = 'SERVICE' as const

/** Domain key of the module statistics (mirrors `PRODUCTS_DOMAIN` in `products-table.tsx`). */
const PRODUCTS_DOMAIN = 'products'

export type ProductFormValues = CreateProductFormValues

interface UseProductFormArgs {
  mode: ProductFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (product: ProductDetail) => void
}

/**
 * Owns every non-render concern of `ProductForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useProductForm({ mode, onSuccess }: UseProductFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(PRODUCTS_DOMAIN)
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'products',
    mode.type === 'edit' ? { type: 'edit', customFields: mode.product.custom_fields } : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateProductSchema(t, customFields.schema)
        : buildCreateProductSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<ProductFormValues>(() => {
    if (mode.type === 'edit') {
      const { product } = mode
      return {
        name: product.name,
        description: product.description,
        cost: product.cost,
        price: product.price,
        category_id: product.category_id,
        product_type: product.product_type,
        vat_rate_id: product.vat_rate_id,
        supplier_id: product.supplier_id,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      name: '',
      description: null,
      cost: null,
      price: null,
      category_id: null,
      product_type: DEFAULT_PRODUCT_TYPE,
      vat_rate_id: null,
      supplier_id: null,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  const form = useForm<ProductFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: ProductFormValues) => {
    setServerError(null)
    const errorFields: Path<ProductFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<ProductFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateProduct(mode.product.id, buildUpdatePayload(values, mode.product))
        queryClient.setQueryData(['products', 'detail', mode.product.id], saved)
        toast.success(t('products.form.updated'))
        invalidateStats()
        onSuccess(saved)
        return
      }

      const created = await createProduct(buildCreatePayload(values))
      toast.success(t('products.form.created'))
      invalidateStats()
      onSuccess(created)
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, errorFields)
      if (!handled) {
        setServerError(t('products.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}
