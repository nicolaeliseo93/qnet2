import { useEffect, useMemo, useRef, useState } from 'react'
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
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { useDefaultSystemStatusId } from '@/features/status-reorder/use-default-system-status'
import type { ProjectDetail, ProjectFormMode } from '@/features/projects/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'

/** Domain key of the module statistics (mirrors `PROJECTS_DOMAIN` in `projects-table.tsx`). */
const PROJECTS_DOMAIN = 'projects'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'code',
  'name',
  'pipeline_status_id',
  'description',
  'business_function_id',
  'country_id',
  'state_id',
  'province_id',
  'city_id',
  'product_category_id',
  'partner_id',
  'operational_site_id',
  'start_date',
  'end_date',
  'total_budget',
  'target_lead',
] as const

export type ProjectFormValues = CreateProjectFormValues

/**
 * Maps every form field shared by edit and duplicate (row action "duplicate")
 * from a loaded project — `code`/`name` are excluded since the two modes
 * diverge there (edit keeps the saved code/name, duplicate clears the code
 * for server regeneration and appends the copy suffix to the name). Kept as
 * a single source of truth so the two `defaultValues` branches below never
 * drift apart.
 */
function mapProjectToFormValues(
  project: ProjectDetail,
  customFieldsDefaultValues: Record<string, CustomFieldValue>,
): Omit<ProjectFormValues, 'code' | 'name'> {
  return {
    description: project.description,
    pipeline_status_id: project.pipeline_status_id,
    business_function_id: project.business_function_id,
    country_id: project.country_id,
    state_id: project.state_id,
    province_id: project.province_id,
    city_id: project.city_id,
    product_category_id: project.product_category_id,
    partner_id: project.partner_id,
    operational_site_id: project.operational_site_id,
    start_date: project.start_date ?? '',
    end_date: project.end_date ?? '',
    total_budget: project.total_budget === null ? null : Number(project.total_budget),
    target_lead: project.target_lead,
    custom_fields: customFieldsDefaultValues,
  }
}

interface UseProjectFormArgs {
  mode: ProjectFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (project: ProjectDetail) => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (spec 0025). */
  initialCode?: string
}

/**
 * Owns every non-render concern of `ProjectForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useProjectForm({ mode, onSuccess, initialCode }: UseProjectFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(PROJECTS_DOMAIN)
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'
  // Only a bare create preselects the system default status below (duplicate
  // must keep the copied source status; edit never applies it either).
  const isStandaloneCreate = mode.type === 'create'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  // Duplicate seeds its values from the source, exactly like edit, even
  // though it submits through the create payload builder.
  const customFields = useCustomFieldsForm(
    'projects',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.project.custom_fields }
      : mode.type === 'duplicate'
        ? { type: 'edit', customFields: mode.source.custom_fields }
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
      return {
        code: mode.project.code,
        name: mode.project.name,
        ...mapProjectToFormValues(mode.project, customFields.defaultValues),
      }
    }
    if (mode.type === 'duplicate') {
      return {
        code: initialCode ?? '',
        name: mode.source.name + t('common.copySuffix'),
        ...mapProjectToFormValues(mode.source, customFields.defaultValues),
      }
    }
    return {
      code: initialCode ?? '',
      name: '',
      description: null,
      pipeline_status_id: null,
      business_function_id: null,
      country_id: null,
      state_id: null,
      province_id: null,
      city_id: null,
      product_category_id: null,
      partner_id: null,
      operational_site_id: null,
      start_date: '',
      end_date: '',
      total_budget: null,
      target_lead: null,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues, initialCode, t])

  const form = useForm<ProjectFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // Spec 0039 D-3: preselect the system "Nuovo" status on a bare create, once
  // the for-select resolves, but only if the field is still untouched — the
  // user (or a faster manual pick) always wins. Gated on `isStandaloneCreate`
  // (not `!isEdit`): duplicate is not edit either, but it already prefilled
  // `pipeline_status_id` from the source and must keep it, not have it
  // silently overwritten by the system default. Guarded to apply at most
  // once: an effect is the correct tool here, since RHF's `defaultValues`
  // are fixed at mount and this value only becomes known asynchronously.
  const defaultStatus = useDefaultSystemStatusId(
    PROJECT_STATUSES_FOR_SELECT_RESOURCE,
    'new',
    isStandaloneCreate,
  )
  const appliedDefaultStatus = useRef(false)
  useEffect(() => {
    if (!isStandaloneCreate || appliedDefaultStatus.current || defaultStatus.data == null) {
      return
    }
    if (form.getFieldState('pipeline_status_id').isDirty) {
      appliedDefaultStatus.current = true
      return
    }
    form.setValue('pipeline_status_id', defaultStatus.data)
    appliedDefaultStatus.current = true
  }, [isStandaloneCreate, defaultStatus.data, form])

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
