import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ProductCategoriesTable } from '@/features/product-categories/product-categories-table'

/**
 * Product Categories page. Light composition only: gates access with
 * `product-categories.viewAny` and mounts the thin Product Categories
 * adapter, which in turn mounts the generic table
 * (`domain="product-categories"`). The generic table owns config loading and
 * loading/error/empty states; no business logic or data fetching lives here
 * (mirrors `ProductsPage`).
 */
export default function ProductCategoriesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="product-categories.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('productCategories.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ProductCategoriesTable />
      </div>
    </Can>
  )
}
