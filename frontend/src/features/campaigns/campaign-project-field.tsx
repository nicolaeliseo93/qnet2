import { useTranslation } from 'react-i18next'
import type { Control, UseFormSetValue } from 'react-hook-form'
import { useQueryClient } from '@tanstack/react-query'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import { PROJECTS_FOR_SELECT_RESOURCE } from '@/features/projects/for-select-api'
import { fetchCampaignProjectMeta } from '@/features/campaigns/use-campaign-project-meta'
import { EMPTY_PROJECT_GEO, lockedLevelsFromProjectGeo } from '@/features/campaigns/campaign-geo'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'
import type { CampaignProjectRef } from '@/features/campaigns/types'

interface CampaignProjectFieldProps {
  control: Control<CampaignFormValues>
  setValue: UseFormSetValue<CampaignFormValues>
  /** The loaded campaign's linked project, if any (edit mode hydration). */
  selected: CampaignProjectRef | null
}

/** Matches `ProjectForSelectResource`'s label: `"{code} — {name}"`. */
function toForSelectItem(ref: CampaignProjectRef | null): ForSelectItem | null {
  return ref ? { id: ref.id, label: `${ref.code} — ${ref.name}` } : null
}

/** The quick-create registry's `projects` entry already composes `name` as `"{code} — {name}"` (see `advanced-entries.tsx`). */
function toForSelectItemFromRef(ref: RelationFieldRef | null): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.name } : null
}

/**
 * The campaign's optional Project link. Picking a project prefills Partner
 * (`partner_id`) and the Sede (`operational_site_id`, project -> campaign ->
 * lead inheritance chain) — both still editable — plus the 3 BR-2
 * classification fields (which the sibling `CampaignRelationField`s then
 * force read-only) and the geo levels the project fills (BR-5, spec 0027: the
 * sibling `<GeoSelect lockedLevels>` then forces those read-only), straight
 * from the picker's own `for-select` `meta` block (AC-042): a single one-shot
 * fetch, run as a direct consequence of the user's selection (never a
 * render-time effect), so it never re-overwrites the user's own edits on a
 * later re-render. Clearing the project resets the 3 classification fields
 * AND all 4 geo levels to `null` (and unlocks them), so they become editable
 * and required again (AC-043); the always-own fields (Partner, Sede) are
 * left untouched.
 */
export function CampaignProjectField({ control, setValue, selected }: CampaignProjectFieldProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { quickCreated, renderAction } = useQuickCreateAction(PROJECTS_FOR_SELECT_RESOURCE)

  const applyProjectSelection = async (projectId: number | null) => {
    if (projectId === null) {
      setValue('pipeline_status_id', null, { shouldValidate: true, shouldDirty: true })
      setValue('business_function_id', null, { shouldValidate: true, shouldDirty: true })
      setValue('product_category_id', null, { shouldValidate: true, shouldDirty: true })
      setValue('country_id', null, { shouldValidate: true, shouldDirty: true })
      setValue('state_id', null, { shouldDirty: true })
      setValue('province_id', null, { shouldDirty: true })
      setValue('city_id', null, { shouldDirty: true })
      setValue('geo_locked_levels', [], { shouldValidate: true, shouldDirty: true })
      return
    }

    const meta = await fetchCampaignProjectMeta(queryClient, projectId)
    if (!meta) {
      return
    }
    setValue('partner_id', meta.partner?.id ?? null, { shouldDirty: true })
    setValue('operational_site_id', meta.operational_site?.id ?? null, { shouldDirty: true })
    setValue('pipeline_status_id', meta.pipeline_status.id, { shouldDirty: true, shouldValidate: true })
    setValue('business_function_id', meta.business_function?.id ?? null, { shouldDirty: true })
    setValue('product_category_id', meta.product_category?.id ?? null, { shouldDirty: true })

    const geo = meta.geo ?? EMPTY_PROJECT_GEO
    setValue('country_id', geo.country?.id ?? null, { shouldDirty: true, shouldValidate: true })
    setValue('state_id', geo.state?.id ?? null, { shouldDirty: true })
    setValue('province_id', geo.province?.id ?? null, { shouldDirty: true })
    setValue('city_id', geo.city?.id ?? null, { shouldDirty: true })
    setValue('geo_locked_levels', lockedLevelsFromProjectGeo(geo), {
      shouldDirty: true,
      shouldValidate: true,
    })
  }

  const selectProject = (field: { onChange: (value: number | null) => void }, projectId: number | null) => {
    field.onChange(projectId)
    void applyProjectSelection(projectId)
  }

  return (
    <MetaField control={control} name="project_id" metaKey="project_id" label={t('campaigns.form.project')}>
      {({ field, disabled }) => {
        const quickCreatedMatch = quickCreated.find((ref: RelationFieldRef) => ref.id === field.value) ?? null
        return (
          <FormControl>
            <AsyncPaginatedSelect
              resource={PROJECTS_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={(projectId) => selectProject(field, projectId)}
              selectedItem={toForSelectItemFromRef(quickCreatedMatch) ?? toForSelectItem(selected)}
              disabled={disabled}
              labels={{
                placeholder: t('campaigns.form.selectPlaceholder'),
                searchPlaceholder: t('campaigns.form.projectSearch'),
                empty: t('campaigns.form.selectEmpty'),
                error: t('campaigns.form.selectError'),
                clearLabel: t('common.clear'),
                triggerLabel: t('campaigns.form.project'),
                retry: t('common.retry'),
              }}
              action={renderAction((ref) => selectProject(field, ref.id), disabled)}
            />
          </FormControl>
        )
      }}
    </MetaField>
  )
}
