import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { opportunityDetailQueryKey } from '@/features/opportunities/api'
import { updateRequestWork } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { buildRequestWorkPayload } from '@/features/request-management/request-work-payload'
import {
  buildRequestWorkSchema,
  type RequestWorkFormValues,
} from '@/features/request-management/request-work-schema'
import type { ApplicableAttribute, RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/** Seeds the dynamic `attribute_values` RHF slice: every applicable code, `null` when unset. */
function seedAttributeValues(
  attributes: ApplicableAttribute[],
  values: Record<string, unknown>,
): Record<string, CustomFieldValue> {
  const seeded: Record<string, CustomFieldValue> = {}
  for (const attribute of attributes) {
    seeded[attribute.code] = (values[attribute.code] as CustomFieldValue | undefined) ?? null
  }
  return seeded
}

function buildDefaultValues(panel: RequestWorkPanelWithPermissions): RequestWorkFormValues {
  return {
    opportunity_workflow_status_id: panel.workflow_status?.id ?? null,
    attribute_values: seedAttributeValues(panel.applicable_attributes, panel.attribute_values),
  }
}

/**
 * Owns the RHF/Zod wiring of the work panel's editable surface (spec 0049
 * AC-061/062): dynamic schema/defaults derived from the loaded panel, sparse
 * PATCH submit (`buildRequestWorkPayload`), 422 mapped onto the working-state
 * field and every `attribute_values.<code>` path (accessible triad via
 * `MetaField`/`FormMessage`, frontend.md §10).
 */
export function useRequestWorkForm(panel: RequestWorkPanelWithPermissions) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(
    () => buildRequestWorkSchema(panel.applicable_attributes, t),
    [panel.applicable_attributes, t],
  )

  const defaultValues = useMemo(() => buildDefaultValues(panel), [panel])

  const form = useForm<RequestWorkFormValues>({ resolver: zodResolver(schema), defaultValues })

  const errorFields: Path<RequestWorkFormValues>[] = [
    'opportunity_workflow_status_id' as Path<RequestWorkFormValues>,
    ...panel.applicable_attributes.map(
      (attribute) => `attribute_values.${attribute.code}` as Path<RequestWorkFormValues>,
    ),
  ]

  const onSubmit = form.handleSubmit(async (values) => {
    setServerError(null)
    const payload = buildRequestWorkPayload(values, panel)
    if (Object.keys(payload).length === 0) {
      return
    }
    try {
      const updated = await updateRequestWork(panel.id, payload)
      queryClient.setQueryData(requestManagementKeys.panel(panel.id), updated)
      queryClient.invalidateQueries({ queryKey: opportunityDetailQueryKey(panel.id) })
      toast.success(t('requestManagement.workPanel.saved', { defaultValue: 'Working data saved.' }))
      form.reset(buildDefaultValues(updated))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(
          t('requestManagement.workPanel.genericError', {
            defaultValue: 'Something went wrong. Please try again.',
          }),
        )
      }
    }
  })

  return { form, onSubmit, serverError, isSubmitting: form.formState.isSubmitting }
}
