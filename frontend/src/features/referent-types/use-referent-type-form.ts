import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createReferentType, updateReferentType } from '@/features/referent-types/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/referent-types/referent-type-form-payload'
import {
  buildCreateReferentTypeSchema,
  buildUpdateReferentTypeSchema,
  type CreateReferentTypeFormValues,
  type UpdateReferentTypeFormValues,
} from '@/features/referent-types/referent-type-schema'
import type {
  ReferentTypeDetail,
  ReferentTypeFormMode,
} from '@/features/referent-types/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name'] as const

export type ReferentTypeFormValues = CreateReferentTypeFormValues & UpdateReferentTypeFormValues

interface UseReferentTypeFormArgs {
  mode: ReferentTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (referentType: ReferentTypeDetail) => void
}

/**
 * Owns every non-render concern of `ReferentTypeForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useReferentTypeForm({ mode, onSuccess }: UseReferentTypeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateReferentTypeSchema(t) : buildCreateReferentTypeSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<ReferentTypeFormValues>(() => {
    if (mode.type === 'edit') {
      return { name: mode.referentType.name }
    }
    return { name: '' }
  }, [mode])

  const form = useForm<ReferentTypeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: ReferentTypeFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateReferentType(
          mode.referentType.id,
          buildUpdatePayload(values, mode.referentType),
        )
        queryClient.setQueryData(['referent-types', 'detail', mode.referentType.id], saved)
        toast.success(t('referentTypes.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createReferentType(buildCreatePayload(values))
      toast.success(t('referentTypes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        setServerError(t('referentTypes.form.genericError'))
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
