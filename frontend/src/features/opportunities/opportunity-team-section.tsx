import { useTranslation } from 'react-i18next'
import { Users } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'

interface OpportunityTeamSectionProps {
  control: Control<OpportunityFormValues>
  selectedItems: OpportunitySelectedItems
  className?: string
}

/**
 * The opportunity's team relations: supervisor and the shared, ordered "G.A. n"
 * manager slots (`ManagerSlotsField`, extracted from Registries — spec 0040).
 */
export function OpportunityTeamSection({ control, selectedItems, className }: OpportunityTeamSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Users}
      title={t('opportunities.form.sections.team.title')}
      description={t('opportunities.form.sections.team.description')}
      className={className}
    >
      <RelationSelectField
        control={control}
        name="supervisor_id"
        metaKey="supervisor_id"
        label={t('opportunities.form.supervisor')}
        resource={USERS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('opportunities.form.supervisorSearch')}
        selected={selectedItems.supervisor}
        showAvatar
        placeholder={t('opportunities.form.selectPlaceholder')}
        emptyLabel={t('opportunities.form.selectEmpty')}
        errorLabel={t('opportunities.form.selectError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />

      <MetaField
        control={control}
        name="manager_slots"
        metaKey="manager_slots"
        label={t('opportunities.form.managers')}
      >
        {({ field, disabled }) => (
          <ManagerSlotsField
            value={field.value}
            onChange={field.onChange}
            selectedItems={selectedItems.managers}
            disabled={disabled}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
