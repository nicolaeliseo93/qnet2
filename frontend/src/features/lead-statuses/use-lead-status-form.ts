import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createLeadStatus, updateLeadStatus } from '@/features/lead-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/lead-statuses/lead-status-form-payload'
import {
  buildCreateLeadStatusSchema,
  buildUpdateLeadStatusSchema,
  type CreateLeadStatusFormValues,
  type UpdateLeadStatusFormValues,
} from '@/features/lead-statuses/lead-status-schema'
import type { LeadStatusDetail, LeadStatusFormMode } from '@/features/lead-statuses/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color', 'status_group_id'] as const

export type LeadStatusFormValues = CreateLeadStatusFormValues & UpdateLeadStatusFormValues

interface UseLeadStatusFormArgs {
  mode: LeadStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (leadStatus: LeadStatusDetail) => void
}

/**
 * Owns every non-render concern of `LeadStatusForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useLeadStatusForm({ mode, onSuccess }: UseLeadStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'lead-statuses',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.leadStatus.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateLeadStatusSchema(t, customFields.schema)
        : buildCreateLeadStatusSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<LeadStatusFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.leadStatus.name,
        color: mode.leadStatus.color ?? '',
        status_group_id: mode.leadStatus.status_group_id,
        custom_fields: customFields.defaultValues,
      }
    }
    return { name: '', color: '', status_group_id: null, custom_fields: customFields.defaultValues }
  }, [mode, customFields.defaultValues])

  const form = useForm<LeadStatusFormValues>({
    // `schema` is a UNION of the create/edit Zod object types (picked at
    // runtime by `isEdit`); `zodResolver` cannot infer its generics from a
    // union schema value and falls back to bare `FieldValues`, which then
    // fails to unify with `LeadStatusFormValues` everywhere `form.control` is
    // passed downstream. `sort_order`'s `z.coerce.number()` additionally
    // widens the schema's OWN inferred input to `unknown` (pre-coercion), so
    // even an explicit `<LeadStatusFormValues, unknown, LeadStatusFormValues>`
    // fails the structural check — asserting the resolver's type at this one
    // boundary (not `any`) is the correct fix: at runtime it still validates
    // through the same schema and yields `LeadStatusFormValues`.
    resolver: zodResolver(schema) as Resolver<LeadStatusFormValues>,
    defaultValues,
  })

  const onSubmit = async (values: LeadStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<LeadStatusFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<LeadStatusFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateLeadStatus(
          mode.leadStatus.id,
          buildUpdatePayload(values, mode.leadStatus),
        )
        queryClient.setQueryData(['lead-statuses', 'detail', mode.leadStatus.id], saved)
        toast.success(t('leadStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createLeadStatus(buildCreatePayload(values))
      toast.success(t('leadStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('leadStatuses.form.genericError'))
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
