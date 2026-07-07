import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { PageHeader } from '@/components/page-header'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Can } from '@/features/auth/can'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { productCategoryColumnRenderers } from '@/features/product-categories/column-renderers'
import { deleteProductCategory, fetchProductCategory } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { ProductCategoryDetail } from '@/features/product-categories/types'

/** Domain key used to mount the generic table for product categories. */
const PRODUCT_CATEGORIES_DOMAIN = 'product-categories'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin Product Categories adapter over the generic table. It mounts
 * `<TableView>` with the `product-categories` domain, its custom cell
 * renderers and a row-action handler, and owns the CRUD flows: opening a
 * Sheet for view/edit/create, confirming + running the delete mutation
 * (surfacing the backend's restrictive-delete 409/422 when a category still
 * has children or products), and refreshing the SSRM grid after every
 * mutation via the table's imperative handle (mirrors `ProductsTable`). The
 * category form's own parent-picker still flattens `GET
 * /product-categories/tree` (see `product-category-form-body.tsx`) — only
 * the LIST surface moved from a custom tree view to this grid.
 */
export function ProductCategoriesTable() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteProductCategory(row.id)
        toast.success(t('productCategories.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('productCategories.form.deleteForbidden'))
        } else if (status === 409 || status === 422) {
          toast.error(t('productCategories.form.deleteInUse'))
        } else {
          toast.error(t('productCategories.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          setSheet({ kind: 'view', row })
          break
        case 'edit':
          setSheet({ kind: 'edit', row })
          break
        case 'delete':
          void runDelete(row)
          break
        default:
          break
      }
    },
    [runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  const onSheetOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        closeSheet()
      }
    },
    [closeSheet],
  )

  const onMutationSuccess = useCallback(
    (category: ProductCategoryDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.setQueryData(productCategoryKeys.detail(category.id), category)
      void queryClient.invalidateQueries({ queryKey: productCategoryKeys.tree })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="product-categories.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('productCategories.form.newRootCategory')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={PRODUCT_CATEGORIES_DOMAIN}
        renderers={productCategoryColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('productCategories.detail.title')}</SheetTitle>
                <SheetDescription>{t('productCategories.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewProductCategoryLoader categoryId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('productCategories.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('productCategories.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <ProductCategoryForm
                mode={{ type: 'create', parentId: null }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('productCategories.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('productCategories.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditProductCategoryLoader
                categoryId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>
    </div>
  )
}

interface ViewProductCategoryLoaderProps {
  categoryId: number
}

/**
 * Fetches the fresh category detail and hands it down to the
 * (presentational) `ProductCategoryDetailView`, which owns no data-fetching
 * state of its own.
 */
function ViewProductCategoryLoader({ categoryId }: ViewProductCategoryLoaderProps) {
  const { t } = useTranslation()
  const {
    data: category,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(productCategoryKeys.detail(categoryId), () => fetchProductCategory(categoryId))

  if (isError) {
    return (
      <DetailError
        message={t('productCategories.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !category) {
    return <DetailLoading />
  }

  return <ProductCategoryDetailView category={category} />
}

interface EditProductCategoryLoaderProps {
  categoryId: number
  onSuccess: (category: ProductCategoryDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized category detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row snapshot.
 */
function EditProductCategoryLoader({ categoryId, onSuccess, onCancel }: EditProductCategoryLoaderProps) {
  const { t } = useTranslation()
  const {
    data: category,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(productCategoryKeys.detail(categoryId), () => fetchProductCategory(categoryId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('productCategories.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !category) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <ProductCategoryForm mode={{ type: 'edit', category }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}
