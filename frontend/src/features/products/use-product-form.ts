import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createProduct, updateProduct } from '@/features/products/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/products/product-form-payload'
import {
  buildCreateProductSchema,
  buildUpdateProductSchema,
  type CreateProductFormValues,
} from '@/features/products/product-schema'
import type { AttributeFieldValue, ProductDetail, ProductFormMode } from '@/features/products/types'

/** Server-side generic field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'description', 'cost', 'price', 'category_id'] as const

export type ProductFormValues = CreateProductFormValues

interface UseProductFormArgs {
  mode: ProductFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (product: ProductDetail) => void
}

/** Builds the `{attribute_id: value}` record hydrating an existing product's dynamic fields. */
function attributesRecordFromDetail(product: ProductDetail): Record<string, AttributeFieldValue> {
  return Object.fromEntries(
    product.attributes.map((attribute) => [String(attribute.attribute_id), attribute.value]),
  )
}

/**
 * Owns every non-render concern of `ProductForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useProductForm({ mode, onSuccess }: UseProductFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateProductSchema(t) : buildCreateProductSchema(t)),
    [isEdit, t],
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
        attributes: attributesRecordFromDetail(product),
      }
    }
    return {
      name: '',
      description: null,
      cost: null,
      price: null,
      category_id: null,
      attributes: {},
    }
  }, [mode])

  const form = useForm<ProductFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: ProductFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const attributesDirty = Boolean(form.formState.dirtyFields.attributes)
        const saved = await updateProduct(
          mode.product.id,
          buildUpdatePayload(values, mode.product, attributesDirty),
        )
        queryClient.setQueryData(['products', 'detail', mode.product.id], saved)
        toast.success(t('products.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createProduct(buildCreatePayload(values))
      toast.success(t('products.form.created'))
      onSuccess(created)
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])
      if (!handled) {
        setServerError(attributeErrorMessage(error) ?? t('products.form.genericError'))
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

/**
 * Best-effort surface for a 422 on a dynamic attribute value: the backend
 * reports these as `attributes.{index}.value` (or similar), which cannot be
 * mapped onto a specific rendered field (no per-attribute RHF path), so it is
 * shown as the form's generic error banner instead of inline.
 */
function attributeErrorMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  const attributeKey = Object.keys(errors ?? {}).find((key) => key.startsWith('attributes.'))
  return attributeKey ? (errors?.[attributeKey]?.[0] ?? null) : null
}
