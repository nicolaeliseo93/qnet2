import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ProductsTable } from '@/features/products/products-table'

/**
 * Products page. Light composition only: gates access with
 * `products.viewAny` and mounts the thin Products adapter, which in turn
 * mounts the generic table (`domain="products"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here (mirrors `ReferentTypesPage`).
 */
export default function ProductsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="products.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('products.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ProductsTable />
      </div>
    </Can>
  )
}
