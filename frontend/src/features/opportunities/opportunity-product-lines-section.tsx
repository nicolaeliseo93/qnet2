import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Boxes } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import { ProductsOfInterestField } from '@/features/products/products-of-interest-field'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunityProductLine, OpportunityProductOfInterest } from '@/features/opportunities/types'

interface OpportunityProductLinesSectionProps {
  control: Control<OpportunityFormValues>
  /** Product-line rows whose labels are already known without a fetch (edit load, from-lead prefill, in-form Lead picker). */
  knownProductLines: OpportunityProductLine[]
  /** Products already on the loaded opportunity (edit), for badge-label hydration. */
  knownProductsOfInterest: OpportunityProductOfInterest[]
  className?: string
}

/**
 * Standalone section (spec 0040 amendment rev.3, AC-106) for the opportunity's
 * function+category product-line rows: its own titled card, separate from the
 * site/classification relations, as the pairs are the module's primary
 * classification axis. Wrapped in `MetaField` (mirrors `manager_slots` in
 * `OpportunityTeamSection`) so a future server-driven field permission and the
 * row-completeness error (`superRefine` in `opportunity-schema.ts`) both
 * surface through the same mechanism as every other field. The row editor
 * itself (spec 0057) is the shared `ProductLinesField`, also consumed by the
 * request-management create form.
 */
export function OpportunityProductLinesSection({
  control,
  knownProductLines,
  knownProductsOfInterest,
  className,
}: OpportunityProductLinesSectionProps) {
  const { t } = useTranslation()

  // The products picker is scoped by the categories currently chosen in the
  // rows above, so editing a row immediately re-scopes it.
  const productLines = useWatch({ control, name: 'product_lines' })
  const selectedCategoryIds = useMemo(
    () => [
      ...new Set(
        productLines
          .map((line) => line.product_category_id)
          .filter((id): id is number => id !== null),
      ),
    ],
    [productLines],
  )

  const knownProducts = useMemo<ForSelectItem[]>(
    () =>
      knownProductsOfInterest.map((product) => ({
        id: product.id,
        label: product.name,
        subtitle: product.product_category?.name ?? null,
      })),
    [knownProductsOfInterest],
  )

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
          <ProductLinesField
            value={field.value}
            onChange={field.onChange}
            knownLines={knownProductLines}
            disabled={disabled}
          />
        )}
      </MetaField>

      {/* Same card as the rows above: the products of interest belong to the
          opportunity's classification, and the picker is scoped by the very
          categories edited right above it (user directive 2026-07-22). */}
      <MetaField
        control={control}
        name="products_of_interest"
        metaKey="products_of_interest"
        label={t('products.ofInterest.fieldLabel')}
      >
        {({ field, disabled }) => (
          <ProductsOfInterestField
            value={field.value}
            onChange={field.onChange}
            categoryIds={selectedCategoryIds}
            selectedItems={knownProducts}
            disabled={disabled}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
