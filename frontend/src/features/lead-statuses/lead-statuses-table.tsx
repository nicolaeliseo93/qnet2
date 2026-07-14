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
import type { ApiErrorResponse } from '@/api/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { leadStatusColumnRenderers } from '@/features/lead-statuses/column-renderers'
import { deleteLeadStatus, fetchLeadStatus } from '@/features/lead-statuses/api'
import { LeadStatusForm } from '@/features/lead-statuses/lead-status-form'
import { LeadStatusDetailView } from '@/features/lead-statuses/lead-status-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { LeadStatusDetail } from '@/features/lead-statuses/types'

/** Domain key used to mount the generic table for lead statuses. */
const LEAD_STATUSES_DOMAIN = 'lead-statuses'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single lead status's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['lead-statuses', 'detail', id] as const
}

/**
 * Thin Lead Statuses adapter over the generic table. It mounts `<TableView>`
 * with the `lead-statuses` domain, its custom cell renderers and a
 * row-action handler, and owns the CRUD flows: opening a Sheet for
 * view/edit/create, confirming + running the delete mutation (surfacing the
 * backend's exact 409 message when the status is still referenced by a
 * Lead, BR-3), and refreshing the SSRM grid after every mutation via the
 * table's imperative handle. No table logic lives here — only
 * lead-statuses CRUD wiring. Permission gating is an affordance only; the
 * backend re-authorizes each call.
 */
export function LeadStatusesTable() {
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
        await deleteLeadStatus(row.id)
        toast.success(t('leadStatuses.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('leadStatuses.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('leadStatuses.form.deleteForbidden'))
        } else if (status === 409) {
          // BR-3: the status is still referenced by a Lead. Surface the
          // backend's own message rather than a generic one.
          toast.error(error.response?.data?.message ?? t('leadStatuses.form.deleteInUseFallback'))
        } else {
          toast.error(t('leadStatuses.form.deleteError'))
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
    (leadStatus: LeadStatusDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(leadStatus.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="lead-statuses.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('leadStatuses.form.newLeadStatus')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={LEAD_STATUSES_DOMAIN}
        renderers={leadStatusColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${LEAD_STATUSES_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('leadStatuses.detail.title')}</SheetTitle>
                <SheetDescription>{t('leadStatuses.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewLeadStatusLoader leadStatusId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('leadStatuses.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('leadStatuses.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <LeadStatusForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('leadStatuses.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('leadStatuses.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditLeadStatusLoader
                leadStatusId={sheet.row.id}
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

interface ViewLeadStatusLoaderProps {
  leadStatusId: number
}

/**
 * Fetches the fresh lead status detail and hands it down to the
 * (presentational) `LeadStatusDetailView`, which owns no data-fetching
 * state of its own.
 */
function ViewLeadStatusLoader({ leadStatusId }: ViewLeadStatusLoaderProps) {
  const { t } = useTranslation()
  const {
    data: leadStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(leadStatusId), () => fetchLeadStatus(leadStatusId))

  if (isError) {
    return (
      <DetailError
        message={t('leadStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !leadStatus) {
    return <DetailLoading />
  }

  return <LeadStatusDetailView leadStatus={leadStatus} />
}

interface EditLeadStatusLoaderProps {
  leadStatusId: number
  onSuccess: (leadStatus: LeadStatusDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized lead status detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than the grid row snapshot.
 */
function EditLeadStatusLoader({ leadStatusId, onSuccess, onCancel }: EditLeadStatusLoaderProps) {
  const { t } = useTranslation()
  const {
    data: leadStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(leadStatusId), () => fetchLeadStatus(leadStatusId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('leadStatuses.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !leadStatus) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <LeadStatusForm mode={{ type: 'edit', leadStatus }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}
