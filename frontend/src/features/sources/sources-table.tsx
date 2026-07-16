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
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { sourceColumnRenderers } from '@/features/sources/column-renderers'
import { deleteSource, fetchSource } from '@/features/sources/api'
import { SourceForm } from '@/features/sources/source-form'
import { SourceDetailView } from '@/features/sources/source-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { SourceDetail } from '@/features/sources/types'

/** Domain key used to mount the generic table for sources. */
const SOURCES_DOMAIN = 'sources'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single source's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['sources', 'detail', id] as const
}

/**
 * Thin Sources adapter over the generic table. It mounts `<TableView>` with
 * the `sources` domain, its custom cell renderers and a row-action handler,
 * and owns the CRUD flows: opening a Sheet for view/edit/create, confirming +
 * running the delete mutation, and refreshing the SSRM grid after every
 * mutation via the table's imperative handle. No table logic lives here —
 * only sources CRUD wiring. Permission gating is an affordance only; the
 * backend re-authorizes each call.
 */
export function SourcesTable() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteSource(row.id)
        toast.success(t('sources.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403 ? t('sources.form.deleteForbidden') : t('sources.form.deleteError'),
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
        case 'activity':
          setActivityRow(row)
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
    (source: SourceDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(source.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="sources.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('sources.form.newSource')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={SOURCES_DOMAIN}
        renderers={sourceColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${SOURCES_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('sources.detail.title')}</SheetTitle>
                <SheetDescription>{t('sources.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewSourceLoader sourceId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('sources.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('sources.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <SourceForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('sources.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('sources.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditSourceLoader
                sourceId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>

      <ResourceActivityDialog
        resource={SOURCES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}

interface ViewSourceLoaderProps {
  sourceId: number
}

/**
 * Fetches the fresh source detail and hands it down to the (presentational)
 * `SourceDetailView`, which owns no data-fetching state of its own.
 */
function ViewSourceLoader({ sourceId }: ViewSourceLoaderProps) {
  const { t } = useTranslation()
  const {
    data: source,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(sourceId), () => fetchSource(sourceId))

  if (isError) {
    return (
      <DetailError
        message={t('sources.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !source) {
    return <DetailLoading />
  }

  return <SourceDetailView source={source} />
}

interface EditSourceLoaderProps {
  sourceId: number
  onSuccess: (source: SourceDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized source detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than
 * the grid row snapshot.
 */
function EditSourceLoader({ sourceId, onSuccess, onCancel }: EditSourceLoaderProps) {
  const { t } = useTranslation()
  const {
    data: source,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(sourceId), () => fetchSource(sourceId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('sources.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !source) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <SourceForm mode={{ type: 'edit', source }} onSuccess={onSuccess} onCancel={onCancel} />
}
