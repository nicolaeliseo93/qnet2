import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'
import type { CampaignRelationRef } from '@/features/campaigns/types'

/**
 * The campaign's 7 single-relation fields, all sharing the exact same picker
 * shape: 3 always-own/editable (registry, source, partner) and 4 BR-2
 * classification fields that are only editable while the campaign is
 * standalone (`forceDisabled` while linked, AC-042/AC-043).
 */
type CampaignRelationFieldName =
  | 'registry_id'
  | 'source_id'
  | 'partner_id'
  | 'project_status_id'
  | 'business_function_id'
  | 'state_id'
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
  /** Forces the field read-only regardless of field permissions: true for the 4 classification fields while `project_id` is set (BR-2). */
  forceDisabled?: boolean
}

/** Renders a `{id, name}` relation ref as the `ForSelectItem` shape `AsyncPaginatedSelect` hydrates from. */
function toForSelectItem(ref: CampaignRelationRef | null): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.name } : null
}

/**
 * One of the campaign's single-relation pickers: an `AsyncPaginatedSelect`
 * inside `MetaField`, hydrated from the loaded detail's `{id, name}`
 * projection. Extracted so `CampaignFormBody` stays within the engineering
 * size limits — mirrors `ProjectRelationField` (spec 0023), duplicated rather
 * than shared cross-feature because it is typed to `CampaignFormValues`.
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
    <MetaField control={control} name={name} metaKey={metaKey} label={label}>
      {({ field, disabled }) => (
        <FormControl>
          <AsyncPaginatedSelect
            resource={resource}
            value={field.value}
            onChange={field.onChange}
            selectedItem={toForSelectItem(selected)}
            disabled={disabled || forceDisabled}
            labels={{
              placeholder: t('campaigns.form.selectPlaceholder'),
              searchPlaceholder,
              empty: t('campaigns.form.selectEmpty'),
              error: t('campaigns.form.selectError'),
              clearLabel: t('common.clear'),
              triggerLabel: label,
              retry: t('common.retry'),
            }}
          />
        </FormControl>
      )}
    </MetaField>
  )
}
