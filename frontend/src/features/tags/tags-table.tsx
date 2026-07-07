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
import { tagColumnRenderers } from '@/features/tags/column-renderers'
import { deleteTag, fetchTag } from '@/features/tags/api'
import { TagForm } from '@/features/tags/tag-form'
import { TagDetailView } from '@/features/tags/tag-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { TagDetail } from '@/features/tags/types'

/** Domain key used to mount the generic table for tags. */
const TAGS_DOMAIN = 'tags'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single tag's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['tags', 'detail', id] as const
}

/**
 * Thin Tags adapter over the generic table. It mounts `<TableView>` with the
 * `tags` domain, its custom cell renderers and a row-action handler, and owns
 * the CRUD flows: opening a Sheet for view/edit/create, confirming + running
 * the delete mutation (surfacing the backend's restrictive-delete 409/422
 * when the tag is still attached to a record, mirrors `ProductCategoriesTable`),
 * and refreshing the SSRM grid after every mutation via the table's
 * imperative handle. No table logic lives here — only tags CRUD wiring.
 * Permission gating is an affordance only; the backend re-authorizes each call.
 */
export function TagsTable() {
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
        await deleteTag(row.id)
        toast.success(t('tags.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('tags.form.deleteForbidden'))
        } else if (status === 409 || status === 422) {
          toast.error(t('tags.form.deleteInUse'))
        } else {
          toast.error(t('tags.form.deleteError'))
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
    (tag: TagDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(tag.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="tags.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('tags.form.newTag')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={TAGS_DOMAIN}
        renderers={tagColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('tags.detail.title')}</SheetTitle>
                <SheetDescription>{t('tags.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewTagLoader tagId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('tags.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('tags.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <TagForm mode={{ type: 'create' }} onSuccess={onMutationSuccess} onCancel={closeSheet} />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('tags.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('tags.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditTagLoader
                tagId={sheet.row.id}
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

interface ViewTagLoaderProps {
  tagId: number
}

/**
 * Fetches the fresh tag detail and hands it down to the (presentational)
 * `TagDetailView`, which owns no data-fetching state of its own.
 */
function ViewTagLoader({ tagId }: ViewTagLoaderProps) {
  const { t } = useTranslation()
  const {
    data: tag,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(tagId), () => fetchTag(tagId))

  if (isError) {
    return (
      <DetailError
        message={t('tags.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !tag) {
    return <DetailLoading />
  }

  return <TagDetailView tag={tag} />
}

interface EditTagLoaderProps {
  tagId: number
  onSuccess: (tag: TagDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized tag detail before mounting the edit form,
 * so the partial PATCH starts from authoritative values rather than the grid
 * row snapshot.
 */
function EditTagLoader({ tagId, onSuccess, onCancel }: EditTagLoaderProps) {
  const { t } = useTranslation()
  const {
    data: tag,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(tagId), () => fetchTag(tagId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('tags.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !tag) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <TagForm mode={{ type: 'edit', tag }} onSuccess={onSuccess} onCancel={onCancel} />
}
