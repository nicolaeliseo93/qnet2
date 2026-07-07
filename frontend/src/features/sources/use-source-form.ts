import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createSource, updateSource } from '@/features/sources/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/sources/source-form-payload'
import {
  buildCreateSourceSchema,
  buildUpdateSourceSchema,
  type CreateSourceFormValues,
  type UpdateSourceFormValues,
} from '@/features/sources/source-schema'
import type { SourceDetail, SourceFormMode } from '@/features/sources/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name'] as const

export type SourceFormValues = CreateSourceFormValues & UpdateSourceFormValues

interface UseSourceFormArgs {
  mode: SourceFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (source: SourceDetail) => void
}

/**
 * Owns every non-render concern of `SourceForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useSourceForm({ mode, onSuccess }: UseSourceFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateSourceSchema(t) : buildCreateSourceSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<SourceFormValues>(() => {
    if (mode.type === 'edit') {
      return { name: mode.source.name }
    }
    return { name: '' }
  }, [mode])

  const form = useForm<SourceFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: SourceFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateSource(mode.source.id, buildUpdatePayload(values, mode.source))
        queryClient.setQueryData(['sources', 'detail', mode.source.id], saved)
        toast.success(t('sources.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createSource(buildCreatePayload(values))
      toast.success(t('sources.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        setServerError(t('sources.form.genericError'))
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
