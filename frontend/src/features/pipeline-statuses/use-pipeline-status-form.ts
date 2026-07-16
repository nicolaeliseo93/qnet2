import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createPipelineStatus, updatePipelineStatus } from '@/features/pipeline-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/pipeline-statuses/pipeline-status-form-payload'
import {
  buildCreatePipelineStatusSchema,
  buildUpdatePipelineStatusSchema,
  type CreatePipelineStatusFormValues,
  type UpdatePipelineStatusFormValues,
} from '@/features/pipeline-statuses/pipeline-status-schema'
import type {
  PipelineStatusDetail,
  PipelineStatusFormMode,
} from '@/features/pipeline-statuses/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color', 'status_group_id'] as const

export type PipelineStatusFormValues = CreatePipelineStatusFormValues & UpdatePipelineStatusFormValues

interface UsePipelineStatusFormArgs {
  mode: PipelineStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (pipelineStatus: PipelineStatusDetail) => void
}

/**
 * Owns every non-render concern of `PipelineStatusForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function usePipelineStatusForm({ mode, onSuccess }: UsePipelineStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'pipeline-statuses',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.pipelineStatus.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdatePipelineStatusSchema(t, customFields.schema)
        : buildCreatePipelineStatusSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<PipelineStatusFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.pipelineStatus.name,
        color: mode.pipelineStatus.color ?? '',
        status_group_id: mode.pipelineStatus.status_group_id,
        custom_fields: customFields.defaultValues,
      }
    }
    return { name: '', color: '', status_group_id: null, custom_fields: customFields.defaultValues }
  }, [mode, customFields.defaultValues])

  const form = useForm<PipelineStatusFormValues>({
    // See `useLeadStatusForm`: `schema` is a create/edit union and
    // `sort_order`'s `z.coerce.number()` widens the schema's own inferred
    // input to `unknown`, so `zodResolver` can't infer/unify its generics —
    // asserting the resolver's type at this one boundary (not `any`) is the
    // fix: at runtime it still validates through the same schema.
    resolver: zodResolver(schema) as Resolver<PipelineStatusFormValues>,
    defaultValues,
  })

  const onSubmit = async (values: PipelineStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<PipelineStatusFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<PipelineStatusFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updatePipelineStatus(
          mode.pipelineStatus.id,
          buildUpdatePayload(values, mode.pipelineStatus),
        )
        queryClient.setQueryData(['pipeline-statuses', 'detail', mode.pipelineStatus.id], saved)
        toast.success(t('pipelineStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createPipelineStatus(buildCreatePayload(values))
      toast.success(t('pipelineStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('pipelineStatuses.form.genericError'))
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
