import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createProjectStatus, updateProjectStatus } from '@/features/project-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/project-statuses/project-status-form-payload'
import {
  buildCreateProjectStatusSchema,
  buildUpdateProjectStatusSchema,
  type CreateProjectStatusFormValues,
  type UpdateProjectStatusFormValues,
} from '@/features/project-statuses/project-status-schema'
import type {
  ProjectStatusDetail,
  ProjectStatusFormMode,
} from '@/features/project-statuses/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color', 'sort_order'] as const

export type ProjectStatusFormValues = CreateProjectStatusFormValues & UpdateProjectStatusFormValues

interface UseProjectStatusFormArgs {
  mode: ProjectStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (projectStatus: ProjectStatusDetail) => void
}

/**
 * Owns every non-render concern of `ProjectStatusForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useProjectStatusForm({ mode, onSuccess }: UseProjectStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'project-statuses',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.projectStatus.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateProjectStatusSchema(t, customFields.schema)
        : buildCreateProjectStatusSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<ProjectStatusFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.projectStatus.name,
        color: mode.projectStatus.color ?? '',
        sort_order: mode.projectStatus.sort_order,
        custom_fields: customFields.defaultValues,
      }
    }
    return { name: '', color: '', sort_order: 0, custom_fields: customFields.defaultValues }
  }, [mode, customFields.defaultValues])

  const form = useForm<ProjectStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: ProjectStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<ProjectStatusFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<ProjectStatusFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateProjectStatus(
          mode.projectStatus.id,
          buildUpdatePayload(values, mode.projectStatus),
        )
        queryClient.setQueryData(['project-statuses', 'detail', mode.projectStatus.id], saved)
        toast.success(t('projectStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createProjectStatus(buildCreatePayload(values))
      toast.success(t('projectStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('projectStatuses.form.genericError'))
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
