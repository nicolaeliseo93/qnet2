import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'
import type { CampaignRelationRef } from '@/features/campaigns/types'

/**
 * The campaign's 6 single-relation fields, all sharing the exact same picker
 * shape: 3 always-own/editable (registry, source, partner) and 3 BR-2
 * classification fields that are only editable while the campaign is
 * standalone (`forceDisabled` while linked, AC-042/AC-043). `state_id` left
 * this group (spec 0027 D-3): it is now one of the 4 geo fields rendered by
 * `<GeoSelect>`, following BR-5 instead.
 */
type CampaignRelationFieldName =
  | 'registry_id'
  | 'source_id'
  | 'partner_id'
  | 'project_status_id'
  | 'business_function_id'
  | 'product_category_id'

interface CampaignRelationFieldProps {
  control: Control<CampaignFormValues>
  name: CampaignRelationFieldName
  metaKey: string
  label: string
  resource: string
  searchPlaceholder: string
  /** The loaded detail's hydrated `{id, name}` projection for this relation (edit mode), or the just-picked project's ref (create mode, AC-042). */
  selected: CampaignRelationRef | null
  /** Forces the field read-only regardless of field permissions: true for the 3 classification fields while `project_id` is set (BR-2). */
  forceDisabled?: boolean
}

/**
 * Campaign-specific i18n binding of the shared `RelationSelectField` (spec
 * 0024 M7). Kept as a thin wrapper (rather than inlining the shared component
 * at every call site in `CampaignFormBody`) so the campaign relation fields'
 * call sites stay unchanged.
 */
export function CampaignRelationField({
  control,
  name,
  metaKey,
  label,
  resource,
  searchPlaceholder,
  selected,
  forceDisabled = false,
}: CampaignRelationFieldProps) {
  const { t } = useTranslation()

  return (
    <RelationSelectField
      control={control}
      name={name}
      metaKey={metaKey}
      label={label}
      resource={resource}
      searchPlaceholder={searchPlaceholder}
      selected={selected}
      forceDisabled={forceDisabled}
      placeholder={t('campaigns.form.selectPlaceholder')}
      emptyLabel={t('campaigns.form.selectEmpty')}
      errorLabel={t('campaigns.form.selectError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}
