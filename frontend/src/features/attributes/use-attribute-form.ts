import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createAttribute, updateAttribute } from '@/features/attributes/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/attributes/attribute-form-payload'
import {
  buildCreateAttributeSchema,
  buildUpdateAttributeSchema,
  type CreateAttributeFormValues,
} from '@/features/attributes/attribute-schema'
import type { AttributeDetail, AttributeFormMode } from '@/features/attributes/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['code', 'name', 'data_type', 'options'] as const

export type AttributeFormValues = CreateAttributeFormValues

interface UseAttributeFormArgs {
  mode: AttributeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (attribute: AttributeDetail) => void
}

/**
 * Owns every non-render concern of `AttributeForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useAttributeForm({ mode, onSuccess }: UseAttributeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateAttributeSchema(t) : buildCreateAttributeSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<AttributeFormValues>(() => {
    if (mode.type === 'edit') {
      const { attribute } = mode
      return {
        code: attribute.code,
        name: attribute.name,
        data_type: attribute.data_type,
        options: [...attribute.options]
          .sort((a, b) => a.sort_order - b.sort_order)
          .map((option) => ({ value: option.value, label: option.label })),
      }
    }
    return { code: '', name: '', data_type: 'STRING', options: [] }
  }, [mode])

  const form = useForm<AttributeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: AttributeFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateAttribute(
          mode.attribute.id,
          buildUpdatePayload(values, mode.attribute),
        )
        queryClient.setQueryData(['attributes', 'detail', mode.attribute.id], saved)
        toast.success(t('attributes.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createAttribute(buildCreatePayload(values))
      toast.success(t('attributes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        setServerError(t('attributes.form.genericError'))
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
