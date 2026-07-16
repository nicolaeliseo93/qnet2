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
import { statusGroupColumnRenderers } from '@/features/status-groups/column-renderers'
import { deleteStatusGroup, fetchStatusGroup } from '@/features/status-groups/api'
import { StatusGroupForm } from '@/features/status-groups/status-group-form'
import { StatusGroupDetailView } from '@/features/status-groups/status-group-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { StatusGroupDetail } from '@/features/status-groups/types'

/** Domain key used to mount the generic table for status groups. */
const STATUS_GROUPS_DOMAIN = 'status-groups'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single status group's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['status-groups', 'detail', id] as const
}

/**
 * Thin Status Groups adapter over the generic table. It mounts `<TableView>`
 * with the `status-groups` domain, its custom cell renderers and a
 * row-action handler, and owns the CRUD flows: opening a Sheet for
 * view/edit/create, confirming + running the delete mutation (surfacing the
 * backend's exact 409 message when the group is still referenced by a
 * status, spec 0039 D-6), and refreshing the SSRM grid after every mutation
 * via the table's imperative handle. No table logic lives here — only
 * status-groups CRUD wiring. Permission gating is an affordance only; the
 * backend re-authorizes each call.
 */
export function StatusGroupsTable() {
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
        await deleteStatusGroup(row.id)
        toast.success(t('statusGroups.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('statusGroups.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('statusGroups.form.deleteForbidden'))
        } else if (status === 409) {
          // Spec 0039 D-6: the group is still referenced by a status.
          // Surface the backend's own message rather than a generic one.
          toast.error(error.response?.data?.message ?? t('statusGroups.form.deleteInUseFallback'))
        } else {
          toast.error(t('statusGroups.form.deleteError'))
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
    (statusGroup: StatusGroupDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(statusGroup.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="status-groups.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('statusGroups.form.newStatusGroup')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={STATUS_GROUPS_DOMAIN}
        renderers={statusGroupColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${STATUS_GROUPS_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('statusGroups.detail.title')}</SheetTitle>
                <SheetDescription>{t('statusGroups.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewStatusGroupLoader statusGroupId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('statusGroups.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('statusGroups.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <StatusGroupForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('statusGroups.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('statusGroups.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditStatusGroupLoader
                statusGroupId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>

      <ResourceActivityDialog
        resource={STATUS_GROUPS_DOMAIN}
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

interface ViewStatusGroupLoaderProps {
  statusGroupId: number
}

/**
 * Fetches the fresh status group detail and hands it down to the
 * (presentational) `StatusGroupDetailView`, which owns no data-fetching
 * state of its own.
 */
function ViewStatusGroupLoader({ statusGroupId }: ViewStatusGroupLoaderProps) {
  const { t } = useTranslation()
  const {
    data: statusGroup,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(statusGroupId), () => fetchStatusGroup(statusGroupId))

  if (isError) {
    return (
      <DetailError
        message={t('statusGroups.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !statusGroup) {
    return <DetailLoading />
  }

  return <StatusGroupDetailView statusGroup={statusGroup} />
}

interface EditStatusGroupLoaderProps {
  statusGroupId: number
  onSuccess: (statusGroup: StatusGroupDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized status group detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than the grid row snapshot.
 */
function EditStatusGroupLoader({ statusGroupId, onSuccess, onCancel }: EditStatusGroupLoaderProps) {
  const { t } = useTranslation()
  const {
    data: statusGroup,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(statusGroupId), () => fetchStatusGroup(statusGroupId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('statusGroups.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !statusGroup) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <StatusGroupForm mode={{ type: 'edit', statusGroup }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}
