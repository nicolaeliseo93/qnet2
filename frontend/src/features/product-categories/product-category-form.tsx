import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useProductCategoryFormMeta } from '@/features/product-categories/use-product-category-form-meta'
import { ProductCategoryFormBody } from '@/features/product-categories/product-category-form-body'
import type {
  ProductCategoryDetail,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'

interface ProductCategoryFormProps {
  mode: ProductCategoryFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (category: ProductCategoryDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a product
 * category. Metadata-driven (spec 0004): resolves the resource's
 * `ResourcePermissions` before rendering — edit mode from the loaded instance
 * detail, create mode from `GET /meta/product-categories` — then hands off to
 * `ProductCategoryFormBody`.
 */
export function ProductCategoryForm(props: ProductCategoryFormProps) {
  const { t } = useTranslation()
  const meta = useProductCategoryFormMeta(props.mode)

  if (meta.status === 'loading') {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <ProductCategoryFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
