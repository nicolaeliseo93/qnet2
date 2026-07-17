import { useTranslation } from 'react-i18next'
import { Boxes } from 'lucide-react'
import type { Control, UseFormSetValue } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import { OpportunityProductLinesField } from '@/features/opportunities/opportunity-product-lines-field'
import type { OpportunityNameAutofill } from '@/features/opportunities/use-opportunity-name-autofill'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunityProductLine } from '@/features/opportunities/types'

interface OpportunityProductLinesSectionProps {
  control: Control<OpportunityFormValues>
  setValue: UseFormSetValue<OpportunityFormValues>
  /** Product-line rows whose labels are already known without a fetch (edit load, from-lead prefill, in-form Lead picker). */
  knownProductLines: OpportunityProductLine[]
  nameAutofill: OpportunityNameAutofill
  className?: string
}

/**
 * Standalone section (spec 0040 amendment rev.3, AC-106) for the opportunity's
 * function+category product-line rows: its own titled card, separate from the
 * site/classification relations, as the pairs are the module's primary
 * classification axis and drive the auto-composed name (AC-107). Wrapped in
 * `MetaField` (mirrors `manager_slots` in `OpportunityTeamSection`) so a
 * future server-driven field permission and the row-completeness error
 * (`superRefine` in `opportunity-schema.ts`) both surface through the same
 * mechanism as every other field.
 */
export function OpportunityProductLinesSection({
  control,
  setValue,
  knownProductLines,
  nameAutofill,
  className,
}: OpportunityProductLinesSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Boxes}
      title={t('opportunities.form.sections.productLines.title')}
      description={t('opportunities.form.sections.productLines.description')}
      className={className}
    >
      <MetaField
        control={control}
        name="product_lines"
        metaKey="product_lines"
        label={t('opportunities.form.productLines.fieldLabel')}
      >
        {({ field, disabled }) => (
          <OpportunityProductLinesField
            value={field.value}
            onChange={field.onChange}
            setValue={setValue}
            knownLines={knownProductLines}
            nameAutofill={nameAutofill}
            disabled={disabled}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
