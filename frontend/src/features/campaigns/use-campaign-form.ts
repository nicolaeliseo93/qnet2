import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { campaignDetailQueryKey, createCampaign, updateCampaign } from '@/features/campaigns/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/campaigns/campaign-form-payload'
import {
  buildCreateCampaignSchema,
  buildUpdateCampaignSchema,
  type CreateCampaignFormValues,
} from '@/features/campaigns/campaign-schema'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { useDefaultSystemStatusId } from '@/features/status-reorder/use-default-system-status'
import type { CampaignDetail, CampaignFormMode } from '@/features/campaigns/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. `total_budget` also carries BR-3's insufficient-budget message. */
const SERVER_ERROR_FIELDS = [
  'code',
  'name',
  'project_id',
  'description',
  'source_id',
  'partner_id',
  'pipeline_status_id',
  'business_function_id',
  'product_category_id',
  'country_id',
  'state_id',
  'province_id',
  'city_id',
  'start_date',
  'end_date',
  'total_budget',
  'target_lead',
] as const

export type CampaignFormValues = CreateCampaignFormValues

interface UseCampaignFormArgs {
  mode: CampaignFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (campaign: CampaignDetail) => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (spec 0025). */
  initialCode?: string
}

/**
 * Owns every non-render concern of `CampaignForm`: RHF/Zod wiring, default
 * values, server 422 mapping (BR-3's budget message included, verbatim) and
 * the create/update submit. The component stays UI-only; this hook is the
 * orchestration point (`onSubmit`).
 */
export function useCampaignForm({ mode, onSuccess, initialCode }: UseCampaignFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'campaigns',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.campaign.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateCampaignSchema(t, customFields.schema)
        : buildCreateCampaignSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<CampaignFormValues>(() => {
    if (mode.type === 'edit') {
      const { campaign } = mode
      return {
        code: campaign.code,
        project_id: campaign.project_id,
        name: campaign.name,
        description: campaign.description,
        source_id: campaign.source_id,
        partner_id: campaign.partner_id,
        pipeline_status_id: campaign.pipeline_status_id,
        business_function_id: campaign.business_function_id,
        product_category_id: campaign.product_category_id,
        country_id: campaign.country_id,
        state_id: campaign.state_id,
        province_id: campaign.province_id,
        city_id: campaign.city_id,
        geo_locked_levels: campaign.geo_locked_levels,
        start_date: campaign.start_date ?? '',
        end_date: campaign.end_date ?? '',
        total_budget: campaign.total_budget === null ? null : Number(campaign.total_budget),
        target_lead: campaign.target_lead,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      code: initialCode ?? '',
      project_id: null,
      name: '',
      description: null,
      source_id: null,
      partner_id: null,
      pipeline_status_id: null,
      business_function_id: null,
      product_category_id: null,
      country_id: null,
      state_id: null,
      province_id: null,
      city_id: null,
      geo_locked_levels: [],
      start_date: '',
      end_date: '',
      total_budget: null,
      target_lead: null,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues, initialCode])

  const form = useForm<CampaignFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // Spec 0039 D-3: preselect the system "Nuovo" status on a standalone
  // create, once the for-select resolves, but only if the field is still
  // untouched — the user (or a faster manual pick, or linking a project,
  // which overwrites it itself) always wins. Guarded to apply at most once:
  // an effect is the correct tool here, since RHF's `defaultValues` are
  // fixed at mount and this value only becomes known asynchronously.
  const defaultStatus = useDefaultSystemStatusId(PROJECT_STATUSES_FOR_SELECT_RESOURCE, 'new', !isEdit)
  const appliedDefaultStatus = useRef(false)
  useEffect(() => {
    if (isEdit || appliedDefaultStatus.current || defaultStatus.data == null) {
      return
    }
    if (form.getValues('project_id') !== null || form.getFieldState('pipeline_status_id').isDirty) {
      appliedDefaultStatus.current = true
      return
    }
    form.setValue('pipeline_status_id', defaultStatus.data)
    appliedDefaultStatus.current = true
  }, [isEdit, defaultStatus.data, form])

  const onSubmit = async (values: CampaignFormValues) => {
    setServerError(null)
    const errorFields: Path<CampaignFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<CampaignFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateCampaign(mode.campaign.id, buildUpdatePayload(values, mode.campaign))
        queryClient.setQueryData(campaignDetailQueryKey(mode.campaign.id), saved)
        toast.success(t('campaigns.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createCampaign(buildCreatePayload(values))
      toast.success(t('campaigns.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('campaigns.form.genericError'))
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
