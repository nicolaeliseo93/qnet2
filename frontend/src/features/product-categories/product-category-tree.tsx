import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { FolderPlus } from 'lucide-react'
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
import { useConfirm } from '@/components/confirm-dialog-context'
import { Can } from '@/features/auth/can'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { deleteProductCategory, fetchProductCategory } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { ProductCategoryTreeNodeRow } from '@/features/product-categories/product-category-tree-node'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import type {
  ProductCategoryDetail,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'

/** Which sheet (if any) is currently open. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create'; parentId: number | null }
  | { kind: 'edit'; categoryId: number }

/** Collects every node id in the tree, used to expand it fully on first load. */
function collectAllIds(nodes: ProductCategoryTreeNode[]): number[] {
  return nodes.flatMap((node) => [node.id, ...collectAllIds(node.children)])
}

/**
 * Custom tree view for product categories (spec AC-022): parent/child,
 * unlimited depth, built entirely from `components/ui/` primitives (no new
 * dependency). Owns the CRUD flows the same way the other CRUD adapters do —
 * a Sheet for create/edit and an imperative confirm before delete — but keyed
 * off tree nodes instead of AG Grid rows.
 */
export function ProductCategoryTree() {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const queryClient = useQueryClient()
  const treeQuery = useProductCategoryTree()

  const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set())
  const [expandedOnce, setExpandedOnce] = useState(false)
  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)

  // Expand every node the first time the tree loads, so the hierarchy is
  // visible immediately instead of collapsed-to-roots. Adjusts state during
  // render (React's documented pattern for "reacting to a value becoming
  // available"), not in an effect — it must run exactly once, before paint.
  if (treeQuery.data && !expandedOnce) {
    setExpandedOnce(true)
    setExpandedIds(new Set(collectAllIds(treeQuery.data)))
  }

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const toggleNode = useCallback((id: number) => {
    setExpandedIds((previous) => {
      const next = new Set(previous)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return next
    })
  }, [])

  const handleAddChild = useCallback((node: ProductCategoryTreeNode) => {
    setSheet({ kind: 'create', parentId: node.id })
  }, [])

  const handleEdit = useCallback((node: ProductCategoryTreeNode) => {
    setSheet({ kind: 'edit', categoryId: node.id })
  }, [])

  const handleDelete = useCallback(
    async (node: ProductCategoryTreeNode) => {
      const confirmed = await confirm({
        title: t('productCategories.tree.deleteConfirmTitle'),
        description: t('productCategories.tree.deleteConfirmDescription', { name: node.name }),
        tone: 'destructive',
        confirmLabel: t('productCategories.form.delete'),
      })
      if (!confirmed) {
        return
      }
      setDeletingId(node.id)
      try {
        await deleteProductCategory(node.id)
        toast.success(t('productCategories.form.deleted'))
        void queryClient.invalidateQueries({ queryKey: productCategoryKeys.tree })
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
    [confirm, queryClient, t],
  )

  const onMutationSuccess = useCallback(
    (category: ProductCategoryDetail) => {
      closeSheet()
      queryClient.setQueryData(productCategoryKeys.detail(category.id), category)
      void queryClient.invalidateQueries({ queryKey: productCategoryKeys.tree })
    },
    [closeSheet, queryClient],
  )

  const onSheetOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        closeSheet()
      }
    },
    [closeSheet],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="product-categories.create">
            <Button onClick={() => setSheet({ kind: 'create', parentId: null })}>
              <FolderPlus aria-hidden="true" />
              {t('productCategories.form.newRootCategory')}
            </Button>
          </Can>
        }
      />

      <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div className="min-h-0 flex-1 overflow-y-auto p-2">
          {treeQuery.isPending ? (
            <div className="flex flex-col gap-2 p-2">
              {Array.from({ length: 6 }).map((_, index) => (
                <Skeleton key={index} className="h-8 w-full" />
              ))}
            </div>
          ) : treeQuery.isError ? (
            <div className="flex flex-col items-start gap-3 p-4">
              <p className="text-sm text-destructive">{t('productCategories.tree.loadError')}</p>
              <Button variant="outline" size="sm" onClick={() => void treeQuery.refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : treeQuery.data.length === 0 ? (
            <p className="p-4 text-sm text-muted-foreground">{t('productCategories.tree.empty')}</p>
          ) : (
            treeQuery.data.map((node) => (
              <ProductCategoryTreeNodeRow
                key={node.id}
                node={node}
                depth={0}
                expandedIds={expandedIds}
                onToggle={toggleNode}
                onAddChild={handleAddChild}
                onEdit={handleEdit}
                onDelete={handleDelete}
              />
            ))
          )}
        </div>
      </div>

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('productCategories.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('productCategories.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <ProductCategoryForm
                mode={{ type: 'create', parentId: sheet.parentId }}
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
                categoryId={sheet.categoryId}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>

      {deletingId !== null ? <span className="sr-only">{t('productCategories.tree.deleting')}</span> : null}
    </div>
  )
}

interface EditProductCategoryLoaderProps {
  categoryId: number
  onSuccess: (category: ProductCategoryDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized category detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * tree node snapshot.
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
        <p className="text-sm text-destructive">{t('productCategories.tree.loadError')}</p>
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
