import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { PackageSearch } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import { ProductsOfInterestField } from '@/features/products/products-of-interest-field'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestProductLine, RequestProductOfInterest } from '@/features/request-management/types'

interface RequestProductsOfInterestProps {
  control: Control<RequestWorkFormValues>
  /** The opportunity's function+category rows: their categories scope the picker. */
  productLines: RequestProductLine[]
  /** The currently persisted products, for badge-label hydration. */
  products: RequestProductOfInterest[]
}

/**
 * The "prodotti di interesse" section of the work panel (user directive
 * 2026-07-22): the operator records, after calling the client, which products
 * the request is about. Rendered with the SAME shared picker as the
 * opportunity form, wrapped in `MetaField` like every other field here so its
 * gating comes from the same server-derived permissions.
 */
export function RequestProductsOfInterest({ control, productLines, products }: RequestProductsOfInterestProps) {
  const { t } = useTranslation()

  const categoryIds = useMemo(
    () => [...new Set(productLines.map((line) => line.product_category.id))],
    [productLines],
  )

  const selectedItems = useMemo<ForSelectItem[]>(
    () =>
      products.map((product) => ({
        id: product.id,
        label: product.name,
        subtitle: product.product_category?.name ?? null,
      })),
    [products],
  )

  return (
    <FormSection
      icon={PackageSearch}
      title={t('products.ofInterest.sectionTitle')}
      description={t('products.ofInterest.sectionDescription')}
    >
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
            categoryIds={categoryIds}
            selectedItems={selectedItems}
            disabled={disabled}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
