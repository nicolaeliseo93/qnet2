import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { Package } from 'lucide-react'
import {
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import { enumLabelOf } from '@/features/config/enum-label'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDecimal } from '@/features/products/column-renderers'
import type { ProductDetail } from '@/features/products/types'

interface ProductDetailViewProps {
  product: ProductDetail
}

/** Renders a dynamic attribute's typed value as display text, em dash when empty. */
function formatAttributeValue(value: ProductDetail['attributes'][number], t: TFunction): string {
  if (value.value === null || value.value === '') {
    return '—'
  }
  if (value.data_type === 'BOOLEAN') {
    return value.value ? t('common.yes') : t('common.no')
  }
  if (value.data_type === 'DECIMAL' && typeof value.value === 'number') {
    return formatDecimal(value.value)
  }
  return String(value.value)
}

/**
 * Read-only detail of a single product. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down
 * (mirrors `ReferentTypeDetailView`). Includes an attributes section for the
 * category's dynamic values.
 */
export function ProductDetailView({ product }: ProductDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(product.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={product.name} icon={<Package />} />}
        title={product.name}
        subtitle={product.category?.name}
      />

      <DetailSection title={t('products.detail.details')}>
        <DetailGrid>
          <DetailField label={t('products.columns.product_type')}>
            <Badge variant="secondary">{enumLabelOf('product_type', product.product_type)}</Badge>
          </DetailField>
          {product.description ? (
            <DetailField label={t('products.columns.description')} full>
              {product.description}
            </DetailField>
          ) : null}
          {product.cost !== null && (
            <DetailField label={t('products.columns.cost')}>{formatDecimal(product.cost)}</DetailField>
          )}
          {product.price !== null && (
            <DetailField label={t('products.columns.price')}>{formatDecimal(product.price)}</DetailField>
          )}
        </DetailGrid>
      </DetailSection>

      {product.attributes.length > 0 && (
        <DetailSection title={t('products.detail.attributes')}>
          <ul className="flex flex-col gap-1.5">
            {product.attributes.map((attribute) => (
              <li key={attribute.attribute_id} className="flex items-center gap-2 text-sm">
                <Badge variant="outline" className="text-xs">
                  {attribute.name}
                </Badge>
                <span className="text-foreground">{formatAttributeValue(attribute, t)}</span>
              </li>
            ))}
          </ul>
        </DetailSection>
      )}

      {createdAt ? (
        <DetailMeta label={t('products.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
