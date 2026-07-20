import { useTranslation } from 'react-i18next'
import { Building2 } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { OPPORTUNITY_STATUSES_FOR_SELECT_RESOURCE } from '@/features/opportunity-statuses/for-select-api'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'

interface OpportunityClassificationSectionProps {
  control: Control<OpportunityFormValues>
  selectedItems: OpportunitySelectedItems
  /** BR-2: keys derived from a linked Lead, forced read-only (spec 0040 MT-6; empty outside that flow). */
  lockedFields: ReadonlySet<string>
  className?: string
}

/**
 * The opportunity's classification relation: source. Split out of
 * `OpportunityFormBody` to stay within the engineering size limits (mirrors
 * `CampaignPlanningSection`).
 */
export function OpportunityClassificationSection({
  control,
  selectedItems,
  lockedFields,
  className,
}: OpportunityClassificationSectionProps) {
  const { t } = useTranslation()

  const selectLabels = {
    placeholder: t('opportunities.form.selectPlaceholder'),
    emptyLabel: t('opportunities.form.selectEmpty'),
    errorLabel: t('opportunities.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }

  return (
    <FormSection
      icon={Building2}
      title={t('opportunities.form.sections.classification.title')}
      description={t('opportunities.form.sections.classification.description')}
      className={className}
    >
      <div className="grid gap-3 sm:grid-cols-2">
        <RelationSelectField
          control={control}
          name="opportunity_status_id"
          metaKey="opportunity_status_id"
          label={t('opportunities.form.opportunityStatus')}
          resource={OPPORTUNITY_STATUSES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.opportunityStatusSearch')}
          selected={selectedItems.opportunityStatus}
          required
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="source_id"
          metaKey="source_id"
          label={t('opportunities.form.source')}
          resource={SOURCES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.sourceSearch')}
          selected={selectedItems.source}
          forceDisabled={lockedFields.has('source_id')}
          {...selectLabels}
        />
      </div>
    </FormSection>
  )
}
