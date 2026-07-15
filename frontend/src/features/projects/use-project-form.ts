import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createProject, projectDetailQueryKey, updateProject } from '@/features/projects/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/projects/project-form-payload'
import {
  buildCreateProjectSchema,
  buildUpdateProjectSchema,
  type CreateProjectFormValues,
} from '@/features/projects/project-schema'
import type { ProjectDetail, ProjectFormMode } from '@/features/projects/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'

/** Domain key of the module statistics (mirrors `PROJECTS_DOMAIN` in `projects-table.tsx`). */
const PROJECTS_DOMAIN = 'projects'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'code',
  'name',
  'pipeline_status_id',
  'description',
  'registry_id',
  'source_id',
  'business_function_id',
  'country_id',
  'state_id',
  'province_id',
  'city_id',
  'product_category_id',
  'partner_id',
  'start_date',
  'end_date',
  'total_budget',
  'target_lead',
] as const

export type ProjectFormValues = CreateProjectFormValues

interface UseProjectFormArgs {
  mode: ProjectFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (project: ProjectDetail) => void
}

/**
 * Owns every non-render concern of `ProjectForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useProjectForm({ mode, onSuccess }: UseProjectFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(PROJECTS_DOMAIN)
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'projects',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.project.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateProjectSchema(t, customFields.schema)
        : buildCreateProjectSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<ProjectFormValues>(() => {
    if (mode.type === 'edit') {
      const { project } = mode
      return {
        code: project.code,
        name: project.name,
        description: project.description,
        registry_id: project.registry_id,
        pipeline_status_id: project.pipeline_status_id,
        source_id: project.source_id,
        business_function_id: project.business_function_id,
        country_id: project.country_id,
        state_id: project.state_id,
        province_id: project.province_id,
        city_id: project.city_id,
        product_category_id: project.product_category_id,
        partner_id: project.partner_id,
        start_date: project.start_date ?? '',
        end_date: project.end_date ?? '',
        total_budget: project.total_budget === null ? null : Number(project.total_budget),
        target_lead: project.target_lead,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      code: '',
      name: '',
      description: null,
      registry_id: null,
      pipeline_status_id: null,
      source_id: null,
      business_function_id: null,
      country_id: null,
      state_id: null,
      province_id: null,
      city_id: null,
      product_category_id: null,
      partner_id: null,
      start_date: '',
      end_date: '',
      total_budget: null,
      target_lead: null,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  const form = useForm<ProjectFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: ProjectFormValues) => {
    setServerError(null)
    const errorFields: Path<ProjectFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<ProjectFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateProject(mode.project.id, buildUpdatePayload(values, mode.project))
        queryClient.setQueryData(projectDetailQueryKey(mode.project.id), saved)
        toast.success(t('projects.form.updated'))
        invalidateStats()
        onSuccess(saved)
        return
      }

      const created = await createProject(buildCreatePayload(values))
      toast.success(t('projects.form.created'))
      invalidateStats()
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('projects.form.genericError'))
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
