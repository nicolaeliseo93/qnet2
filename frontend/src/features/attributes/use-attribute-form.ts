import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
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
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

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

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'attributes',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.attribute.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateAttributeSchema(t, customFields.schema)
        : buildCreateAttributeSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
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
        custom_fields: customFields.defaultValues,
      }
    }
    return { code: '', name: '', data_type: 'STRING', options: [], custom_fields: customFields.defaultValues }
  }, [mode, customFields.defaultValues])

  const form = useForm<AttributeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: AttributeFormValues) => {
    setServerError(null)
    const errorFields: Path<AttributeFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<AttributeFormValues>[]),
    ]
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
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
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
