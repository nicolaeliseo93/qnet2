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
import type { ApiErrorResponse } from '@/api/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { pipelineStatusColumnRenderers } from '@/features/pipeline-statuses/column-renderers'
import { deletePipelineStatus, fetchPipelineStatus } from '@/features/pipeline-statuses/api'
import { PipelineStatusForm } from '@/features/pipeline-statuses/pipeline-status-form'
import { PipelineStatusDetailView } from '@/features/pipeline-statuses/pipeline-status-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { PipelineStatusDetail } from '@/features/pipeline-statuses/types'

/** Domain key used to mount the generic table for project statuses. */
const PROJECT_STATUSES_DOMAIN = 'pipeline-statuses'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single project status's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['pipeline-statuses', 'detail', id] as const
}

/**
 * Thin Project Statuses adapter over the generic table. It mounts
 * `<TableView>` with the `pipeline-statuses` domain, its custom cell
 * renderers and a row-action handler, and owns the CRUD flows: opening a
 * Sheet for view/edit/create, confirming + running the delete mutation
 * (surfacing the backend's exact 409 message when the status is still
 * referenced by a Project or a Campaign, BR-4), and refreshing the SSRM grid
 * after every mutation via the table's imperative handle. No table logic
 * lives here — only pipeline-statuses CRUD wiring. Permission gating is an
 * affordance only; the backend re-authorizes each call.
 */
export function PipelineStatusesTable() {
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
        await deletePipelineStatus(row.id)
        toast.success(t('pipelineStatuses.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('pipelineStatuses.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('pipelineStatuses.form.deleteForbidden'))
        } else if (status === 409) {
          // BR-4: the status is still referenced by a Project or a Campaign.
          // Surface the backend's own message rather than a generic one.
          toast.error(error.response?.data?.message ?? t('pipelineStatuses.form.deleteInUseFallback'))
        } else {
          toast.error(t('pipelineStatuses.form.deleteError'))
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
    (pipelineStatus: PipelineStatusDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(pipelineStatus.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="pipeline-statuses.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('pipelineStatuses.form.newPipelineStatus')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={PROJECT_STATUSES_DOMAIN}
        renderers={pipelineStatusColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${PROJECT_STATUSES_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('pipelineStatuses.detail.title')}</SheetTitle>
                <SheetDescription>{t('pipelineStatuses.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewPipelineStatusLoader pipelineStatusId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('pipelineStatuses.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('pipelineStatuses.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <PipelineStatusForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('pipelineStatuses.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('pipelineStatuses.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditPipelineStatusLoader
                pipelineStatusId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>

      <ResourceActivityDialog
        resource={PROJECT_STATUSES_DOMAIN}
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

interface ViewPipelineStatusLoaderProps {
  pipelineStatusId: number
}

/**
 * Fetches the fresh project status detail and hands it down to the
 * (presentational) `PipelineStatusDetailView`, which owns no data-fetching
 * state of its own.
 */
function ViewPipelineStatusLoader({ pipelineStatusId }: ViewPipelineStatusLoaderProps) {
  const { t } = useTranslation()
  const {
    data: pipelineStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(pipelineStatusId), () => fetchPipelineStatus(pipelineStatusId))

  if (isError) {
    return (
      <DetailError
        message={t('pipelineStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !pipelineStatus) {
    return <DetailLoading />
  }

  return <PipelineStatusDetailView pipelineStatus={pipelineStatus} />
}

interface EditPipelineStatusLoaderProps {
  pipelineStatusId: number
  onSuccess: (pipelineStatus: PipelineStatusDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized project status detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than the grid row snapshot.
 */
function EditPipelineStatusLoader({
  pipelineStatusId,
  onSuccess,
  onCancel,
}: EditPipelineStatusLoaderProps) {
  const { t } = useTranslation()
  const {
    data: pipelineStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(pipelineStatusId), () => fetchPipelineStatus(pipelineStatusId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('pipelineStatuses.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !pipelineStatus) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <PipelineStatusForm
      mode={{ type: 'edit', pipelineStatus }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}
