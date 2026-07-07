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
import { productColumnRenderers } from '@/features/products/column-renderers'
import { deleteProduct, fetchProduct } from '@/features/products/api'
import { ProductForm } from '@/features/products/product-form'
import { ProductDetailView } from '@/features/products/product-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { ProductDetail } from '@/features/products/types'

/** Domain key used to mount the generic table for products. */
const PRODUCTS_DOMAIN = 'products'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single product's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['products', 'detail', id] as const
}

/**
 * Thin Products adapter over the generic table. It mounts `<TableView>` with
 * the `products` domain, its custom cell renderers and a row-action handler,
 * and owns the CRUD flows: opening a Sheet for view/edit/create, confirming +
 * running the delete mutation, and refreshing the SSRM grid after every
 * mutation via the table's imperative handle (mirrors `ReferentTypesTable`).
 */
export function ProductsTable() {
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
        await deleteProduct(row.id)
        toast.success(t('products.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403 ? t('products.form.deleteForbidden') : t('products.form.deleteError'),
        )
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
    (product: ProductDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(product.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="products.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('products.form.newProduct')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={PRODUCTS_DOMAIN}
        renderers={productColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('products.detail.title')}</SheetTitle>
                <SheetDescription>{t('products.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewProductLoader productId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('products.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('products.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <ProductForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('products.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('products.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditProductLoader
                productId={sheet.row.id}
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

interface ViewProductLoaderProps {
  productId: number
}

/**
 * Fetches the fresh product detail and hands it down to the (presentational)
 * `ProductDetailView`, which owns no data-fetching state of its own.
 */
function ViewProductLoader({ productId }: ViewProductLoaderProps) {
  const { t } = useTranslation()
  const {
    data: product,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(productId), () => fetchProduct(productId))

  if (isError) {
    return (
      <DetailError
        message={t('products.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !product) {
    return <DetailLoading />
  }

  return <ProductDetailView product={product} />
}

interface EditProductLoaderProps {
  productId: number
  onSuccess: (product: ProductDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized product detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row snapshot.
 */
function EditProductLoader({ productId, onSuccess, onCancel }: EditProductLoaderProps) {
  const { t } = useTranslation()
  const {
    data: product,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(productId), () => fetchProduct(productId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('products.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !product) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <ProductForm mode={{ type: 'edit', product }} onSuccess={onSuccess} onCancel={onCancel} />
}
