import { useTranslation } from 'react-i18next'
import { FolderTree } from 'lucide-react'
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
import { formatDateTime } from '@/features/table/cell-renderers'
import { enumLabelOf } from '@/features/config/enum-label'
import type { ProductCategoryDetail } from '@/features/product-categories/types'

interface ProductCategoryDetailViewProps {
  category: ProductCategoryDetail
}

/**
 * Read-only detail of a single product category. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down (mirrors `AttributeDetailView`/`ProductDetailView`). Lists both the
 * category's own attribute assignments and what it inherits from its ancestry.
 */
export function ProductCategoryDetailView({ category }: ProductCategoryDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(category.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={category.name} icon={<FolderTree />} />}
        title={category.name}
        subtitle={category.parent?.name}
      />

      {category.description && (
        <DetailSection title={t('productCategories.form.description')}>
          <DetailGrid>
            <DetailField label={t('productCategories.form.description')} full>
              {category.description}
            </DetailField>
          </DetailGrid>
        </DetailSection>
      )}

      {category.attributes.length > 0 && (
        <DetailSection title={t('productCategories.form.attributes')}>
          <ul className="flex flex-col gap-1.5">
            {category.attributes.map((attribute) => (
              <li key={attribute.attribute_id} className="flex items-center gap-2 text-sm">
                <Badge variant="outline" className="text-xs">
                  {enumLabelOf('attribute_type', attribute.data_type)}
                </Badge>
                <span className="text-foreground">{attribute.name}</span>
                {attribute.is_required && (
                  <Badge variant="outline" className="text-xs">
                    {t('productCategories.form.isRequired')}
                  </Badge>
                )}
              </li>
            ))}
          </ul>
        </DetailSection>
      )}

      {category.inherited_attributes.length > 0 && (
        <DetailSection title={t('productCategories.form.inheritedAttributes')}>
          <ul className="flex flex-col gap-1.5">
            {category.inherited_attributes.map((attribute) => (
              <li
                key={attribute.attribute_id}
                className="flex items-center gap-2 text-sm text-muted-foreground"
              >
                <Badge variant="outline" className="text-xs">
                  {enumLabelOf('attribute_type', attribute.data_type)}
                </Badge>
                <span>{attribute.name}</span>
              </li>
            ))}
          </ul>
        </DetailSection>
      )}

      {createdAt ? (
        <DetailMeta label={t('productCategories.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
