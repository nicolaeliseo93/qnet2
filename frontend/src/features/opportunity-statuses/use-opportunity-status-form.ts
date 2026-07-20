import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createOpportunityStatus,
  updateOpportunityStatus,
} from '@/features/opportunity-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/opportunity-statuses/opportunity-status-form-payload'
import {
  buildCreateOpportunityStatusSchema,
  buildUpdateOpportunityStatusSchema,
  type CreateOpportunityStatusFormValues,
  type UpdateOpportunityStatusFormValues,
} from '@/features/opportunity-statuses/opportunity-status-schema'
import type {
  OpportunityStatusDetail,
  OpportunityStatusFormMode,
} from '@/features/opportunity-statuses/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color', 'group'] as const

export type OpportunityStatusFormValues = CreateOpportunityStatusFormValues &
  UpdateOpportunityStatusFormValues

interface UseOpportunityStatusFormArgs {
  mode: OpportunityStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (opportunityStatus: OpportunityStatusDetail) => void
}

/**
 * Owns every non-render concern of `OpportunityStatusForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useOpportunityStatusForm({ mode, onSuccess }: UseOpportunityStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateOpportunityStatusSchema(t) : buildCreateOpportunityStatusSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<OpportunityStatusFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.opportunityStatus.name,
        color: mode.opportunityStatus.color ?? '',
        group: mode.opportunityStatus.group,
      }
    }
    return { name: '', color: '', group: 'open' }
  }, [mode])

  const form = useForm<OpportunityStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: OpportunityStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<OpportunityStatusFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateOpportunityStatus(
          mode.opportunityStatus.id,
          buildUpdatePayload(values, mode.opportunityStatus),
        )
        queryClient.setQueryData(['opportunity-statuses', 'detail', mode.opportunityStatus.id], saved)
        toast.success(t('opportunityStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createOpportunityStatus(buildCreatePayload(values))
      toast.success(t('opportunityStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('opportunityStatuses.form.genericError'))
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
