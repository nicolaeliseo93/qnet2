import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ProductCategoryTree } from '@/features/product-categories/product-category-tree'

/**
 * Product Categories page. Light composition only: gates access with
 * `product-categories.viewAny` and mounts the tree view, which owns its own
 * data fetching and loading/error/empty states.
 */
export default function ProductCategoriesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="product-categories.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('productCategories.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ProductCategoryTree />
      </div>
    </Can>
  )
}
