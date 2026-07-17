import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createLead, leadDetailQueryKey, updateLead } from '@/features/leads/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/leads/lead-form-payload'
import { recordToEntries } from '@/features/leads/extra-fields'
import {
  buildCreateLeadSchema,
  buildUpdateLeadSchema,
  type CreateLeadFormValues,
} from '@/features/leads/lead-schema'
import { LEAD_STATUSES_FOR_SELECT_RESOURCE } from '@/features/lead-statuses/for-select-api'
import { useDefaultSystemStatusId } from '@/features/status-reorder/use-default-system-status'
import type { LeadDetail, LeadFormMode } from '@/features/leads/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'registry_id',
  'campaign_id',
  'lead_status_id',
  'operational_site_id',
  'source_id',
  'operator_id',
  'notes',
] as const

export type LeadFormValues = CreateLeadFormValues

interface UseLeadFormArgs {
  mode: LeadFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (lead: LeadDetail) => void
}

/**
 * Owns every non-render concern of `LeadForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useLeadForm({ mode, onSuccess }: UseLeadFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateLeadSchema(t) : buildCreateLeadSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<LeadFormValues>(() => {
    if (mode.type === 'edit') {
      const { lead } = mode
      return {
        registry_id: lead.registry_id,
        campaign_id: lead.campaign_id,
        lead_status_id: lead.lead_status_id,
        operational_site_id: lead.operational_site_id,
        source_id: lead.source_id,
        operator_id: lead.operator_id,
        notes: lead.notes,
        extra_fields: recordToEntries(lead.extra_fields),
      }
    }
    return {
      registry_id: null,
      campaign_id: null,
      lead_status_id: null,
      operational_site_id: null,
      source_id: null,
      operator_id: null,
      notes: null,
      extra_fields: [],
    }
  }, [mode])

  const form = useForm<LeadFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // Spec 0039 D-3: preselect the system "Nuovo" status on create, once the
  // for-select resolves, but only if the field is still untouched — the
  // user (or a faster manual pick) always wins. Guarded to apply at most
  // once: an effect is the correct tool here, since RHF's `defaultValues`
  // are fixed at mount and this value only becomes known asynchronously.
  const defaultStatus = useDefaultSystemStatusId(LEAD_STATUSES_FOR_SELECT_RESOURCE, 'new', !isEdit)
  const appliedDefaultStatus = useRef(false)
  useEffect(() => {
    if (isEdit || appliedDefaultStatus.current || defaultStatus.data == null) {
      return
    }
    if (form.getFieldState('lead_status_id').isDirty) {
      appliedDefaultStatus.current = true
      return
    }
    form.setValue('lead_status_id', defaultStatus.data)
    appliedDefaultStatus.current = true
  }, [isEdit, defaultStatus.data, form])

  const onSubmit = async (values: LeadFormValues) => {
    setServerError(null)
    const errorFields: Path<LeadFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateLead(mode.lead.id, buildUpdatePayload(values, mode.lead))
        queryClient.setQueryData(leadDetailQueryKey(mode.lead.id), saved)
        toast.success(t('leads.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createLead(buildCreatePayload(values))
      toast.success(t('leads.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('leads.form.genericError'))
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
