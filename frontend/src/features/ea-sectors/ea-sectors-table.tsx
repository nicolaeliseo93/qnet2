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
import { eaSectorColumnRenderers } from '@/features/ea-sectors/column-renderers'
import { deleteEaSector, fetchEaSector } from '@/features/ea-sectors/api'
import { eaSectorKeys } from '@/features/ea-sectors/query-keys'
import { EaSectorForm } from '@/features/ea-sectors/ea-sector-form'
import { EaSectorDetailView } from '@/features/ea-sectors/ea-sector-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { EaSectorDetail } from '@/features/ea-sectors/types'

/** Domain key used to mount the generic table for EA sectors. */
const EA_SECTORS_DOMAIN = 'ea-sectors'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin EA Sectors adapter over the generic table. It mounts `<TableView>`
 * with the `ea-sectors` domain, its custom cell renderers and a row-action
 * handler, and owns the CRUD flows: opening a Sheet for view/edit/create,
 * confirming + running the delete mutation (surfacing the backend's
 * restrictive-delete 409 when a sector still has children), and refreshing
 * both the SSRM grid and the parent-picker tree after every mutation.
 */
export function EaSectorsTable() {
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
        await deleteEaSector(row.id)
        toast.success(t('eaSectors.form.deleted'))
        refreshGrid()
        void queryClient.invalidateQueries({ queryKey: eaSectorKeys.tree })
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('eaSectors.form.deleteForbidden'))
        } else if (status === 409) {
          toast.error(t('eaSectors.form.deleteInUse'))
        } else {
          toast.error(t('eaSectors.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, queryClient, t],
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
    (sector: EaSectorDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.setQueryData(eaSectorKeys.detail(sector.id), sector)
      void queryClient.invalidateQueries({ queryKey: eaSectorKeys.tree })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="ea-sectors.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('eaSectors.form.newEaSector')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={EA_SECTORS_DOMAIN}
        renderers={eaSectorColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('eaSectors.detail.title')}</SheetTitle>
                <SheetDescription>{t('eaSectors.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewEaSectorLoader sectorId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('eaSectors.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('eaSectors.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <EaSectorForm
                mode={{ type: 'create', parentId: null }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('eaSectors.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('eaSectors.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditEaSectorLoader
                sectorId={sheet.row.id}
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

interface ViewEaSectorLoaderProps {
  sectorId: number
}

/**
 * Fetches the fresh sector detail and hands it down to the (presentational)
 * `EaSectorDetailView`, which owns no data-fetching state of its own.
 */
function ViewEaSectorLoader({ sectorId }: ViewEaSectorLoaderProps) {
  const { t } = useTranslation()
  const {
    data: sector,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(eaSectorKeys.detail(sectorId), () => fetchEaSector(sectorId))

  if (isError) {
    return (
      <DetailError
        message={t('eaSectors.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !sector) {
    return <DetailLoading />
  }

  return <EaSectorDetailView sector={sector} />
}

interface EditEaSectorLoaderProps {
  sectorId: number
  onSuccess: (sector: EaSectorDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized sector detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than
 * the grid row snapshot.
 */
function EditEaSectorLoader({ sectorId, onSuccess, onCancel }: EditEaSectorLoaderProps) {
  const { t } = useTranslation()
  const {
    data: sector,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(eaSectorKeys.detail(sectorId), () => fetchEaSector(sectorId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('eaSectors.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !sector) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <EaSectorForm mode={{ type: 'edit', sector }} onSuccess={onSuccess} onCancel={onCancel} />
}
