import { useTranslation } from 'react-i18next'
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

/**
 * Read-only detail of a single product. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down
 * (mirrors `ReferentTypeDetailView`).
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
          {product.business_function && (
            <DetailField label={t('products.columns.business_function')}>
              {product.business_function.name}
            </DetailField>
          )}
        </DetailGrid>
      </DetailSection>

      {createdAt ? (
        <DetailMeta label={t('products.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
