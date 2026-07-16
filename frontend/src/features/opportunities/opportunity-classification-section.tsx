import { useTranslation } from 'react-i18next'
import { Building2 } from 'lucide-react'
import type { Control, UseFormGetValues, UseFormSetValue } from 'react-hook-form'
import { useWatch } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { OpportunityProductCategoryField } from '@/features/opportunities/opportunity-product-category-field'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'

interface OpportunityClassificationSectionProps {
  control: Control<OpportunityFormValues>
  setValue: UseFormSetValue<OpportunityFormValues>
  getValues: UseFormGetValues<OpportunityFormValues>
  selectedItems: OpportunitySelectedItems
  /** BR-2: keys derived from a linked Lead, forced read-only (spec 0040 MT-6; empty outside that flow). */
  lockedFields: ReadonlySet<string>
  className?: string
}

/**
 * The opportunity's site/classification relations: company (+ its site,
 * BR-4: `company_site` scoped by `company_id`), operational site (BR-4:
 * scoped by `business_function_id`), business function, product category
 * (BR-4: prefills the function when picked) and source. Split out of
 * `OpportunityFormBody` to stay within the engineering size limits (mirrors
 * `CampaignPlanningSection`).
 */
export function OpportunityClassificationSection({
  control,
  setValue,
  getValues,
  selectedItems,
  lockedFields,
  className,
}: OpportunityClassificationSectionProps) {
  const { t } = useTranslation()
  const companyId = useWatch({ control, name: 'company_id' })
  const businessFunctionId = useWatch({ control, name: 'business_function_id' })

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
          name="company_id"
          metaKey="company_id"
          label={t('opportunities.form.company')}
          resource={COMPANIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.companySearch')}
          selected={selectedItems.company}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="company_site_id"
          metaKey="company_site_id"
          label={t('opportunities.form.companySite')}
          resource={COMPANY_SITES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.companySiteSearch')}
          selected={selectedItems.companySite}
          params={companyId !== null ? { company_id: companyId } : undefined}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="business_function_id"
          metaKey="business_function_id"
          label={t('opportunities.form.businessFunction')}
          resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.businessFunctionSearch')}
          selected={selectedItems.businessFunction}
          forceDisabled={lockedFields.has('business_function_id')}
          {...selectLabels}
        />

        <RelationSelectField
          control={control}
          name="operational_site_id"
          metaKey="operational_site_id"
          label={t('opportunities.form.operationalSite')}
          resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('opportunities.form.operationalSiteSearch')}
          selected={selectedItems.operationalSite}
          params={businessFunctionId !== null ? { business_function_id: businessFunctionId } : undefined}
          forceDisabled={lockedFields.has('operational_site_id')}
          {...selectLabels}
        />

        <OpportunityProductCategoryField
          control={control}
          setValue={setValue}
          getValues={getValues}
          selected={selectedItems.productCategory}
          forceDisabled={lockedFields.has('product_category_id')}
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
