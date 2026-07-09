import type { ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2, Landmark, Settings, Users } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import { CompanySiteReadonlyField } from '@/features/company-sites/company-site-readonly-field'
import type { CompanySiteDetail } from '@/features/company-sites/types'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'

interface SettingsTabContentProps {
  control: Control<CompanySiteFormValues>
  /** Read-only display of the site's `quotation_*` ids (edit mode only). */
  companySite: CompanySiteDetail | null
  selectedCompanyItem: ForSelectItem | null
  selectedResponsibleRdaItem: ForSelectItem | null
  selectedResponsibleTicketsItem: ForSelectItem | null
  selectedResponsibleValidationContractsItem: ForSelectItem | null
  selectedResponsibleValidationContractsTwoItem: ForSelectItem | null
}

/** Renders a nullable-number field bound through RHF's plain `field` (no MetaField control needed beyond disabled). */
function numberFieldProps(
  field: { value: number | null; onChange: (value: number | null) => void },
) {
  return {
    type: 'number' as const,
    value: field.value ?? '',
    onChange: (event: ChangeEvent<HTMLInputElement>) =>
      field.onChange(event.target.value === '' ? null : Number(event.target.value)),
  }
}

/**
 * Impostazioni tab: the owning company, the four users-backed responsibles,
 * the two document progressives, and the always read-only `quotation_*` ids
 * (backend ceiling forces them readonly regardless of role, so they use
 * `CompanySiteReadonlyField`, not `MetaField`). The preferred bank is a per-row
 * flag in the Banche tab, not a field here (spec 0020 update).
 */
export function SettingsTabContent({
  control,
  companySite,
  selectedCompanyItem,
  selectedResponsibleRdaItem,
  selectedResponsibleTicketsItem,
  selectedResponsibleValidationContractsItem,
  selectedResponsibleValidationContractsTwoItem,
}: SettingsTabContentProps) {
  const { t } = useTranslation()

  const responsibleFields: Array<{
    name:
      | 'responsible_rda_id'
      | 'responsible_tickets_id'
      | 'responsible_validation_contracts_id'
      | 'responsible_validation_contracts_two_id'
    label: string
    selectedItem: ForSelectItem | null
  }> = [
    {
      name: 'responsible_rda_id',
      label: t('companySites.form.responsibleRda'),
      selectedItem: selectedResponsibleRdaItem,
    },
    {
      name: 'responsible_tickets_id',
      label: t('companySites.form.responsibleTickets'),
      selectedItem: selectedResponsibleTicketsItem,
    },
    {
      name: 'responsible_validation_contracts_id',
      label: t('companySites.form.responsibleValidationContracts'),
      selectedItem: selectedResponsibleValidationContractsItem,
    },
    {
      name: 'responsible_validation_contracts_two_id',
      label: t('companySites.form.responsibleValidationContractsTwo'),
      selectedItem: selectedResponsibleValidationContractsTwoItem,
    },
  ]

  return (
    <>
      <FormSection
        icon={Building2}
        title={t('companySites.form.sections.company.title')}
        description={t('companySites.form.sections.company.description')}
      >
        <MetaField
          control={control}
          name="company_id"
          metaKey="company_id"
          label={t('companySites.form.company')}
        >
          {({ field, disabled }) => (
            <FormControl>
              <AsyncPaginatedSelect
                resource={COMPANIES_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItem={selectedCompanyItem}
                disabled={disabled}
                labels={{
                  placeholder: t('companySites.form.companyPlaceholder'),
                  searchPlaceholder: t('companySites.form.companySearch'),
                  empty: t('companySites.form.companyEmpty'),
                  error: t('companySites.form.companyError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('companySites.form.company'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
          )}
        </MetaField>
      </FormSection>

      <FormSection
        icon={Users}
        title={t('companySites.form.sections.responsibles.title')}
        description={t('companySites.form.sections.responsibles.description')}
      >
        {responsibleFields.map(({ name, label, selectedItem }) => (
          <MetaField key={name} control={control} name={name} metaKey={name} label={label}>
            {({ field, disabled }) => (
              <FormControl>
                <AsyncPaginatedSelect
                  resource={USERS_FOR_SELECT_RESOURCE}
                  value={field.value}
                  onChange={field.onChange}
                  selectedItem={selectedItem}
                  showAvatar
                  disabled={disabled}
                  labels={{
                    placeholder: t('companySites.form.responsiblePlaceholder'),
                    searchPlaceholder: t('companySites.form.responsibleSearch'),
                    empty: t('companySites.form.responsibleEmpty'),
                    error: t('companySites.form.responsibleError'),
                    clearLabel: t('common.clear'),
                    triggerLabel: label,
                    retry: t('common.retry'),
                  }}
                />
              </FormControl>
            )}
          </MetaField>
        ))}
      </FormSection>

      <FormSection
        icon={Landmark}
        title={t('companySites.form.sections.banking.title')}
        description={t('companySites.form.sections.banking.description')}
      >
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <MetaField
            control={control}
            name="proforma_progressive"
            metaKey="proforma_progressive"
            label={t('companySites.form.proformaProgressive')}
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input disabled={disabled} readOnly={readOnly} {...numberFieldProps(field)} />
              </FormControl>
            )}
          </MetaField>

          <MetaField
            control={control}
            name="invoice_progressive"
            metaKey="invoice_progressive"
            label={t('companySites.form.invoiceProgressive')}
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input disabled={disabled} readOnly={readOnly} {...numberFieldProps(field)} />
              </FormControl>
            )}
          </MetaField>
        </div>
      </FormSection>

      <FormSection
        icon={Settings}
        title={t('companySites.form.sections.quotation.title')}
        description={t('companySites.form.sections.quotation.description')}
      >
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <CompanySiteReadonlyField
            metaKey="quotation_layout_id"
            label={t('companySites.form.quotationLayout')}
            value={companySite?.quotation_layout_id}
          />
          <CompanySiteReadonlyField
            metaKey="quotation_header_id"
            label={t('companySites.form.quotationHeader')}
            value={companySite?.quotation_header_id}
          />
          <CompanySiteReadonlyField
            metaKey="quotation_footer_id"
            label={t('companySites.form.quotationFooter')}
            value={companySite?.quotation_footer_id}
          />
        </div>
      </FormSection>
    </>
  )
}
