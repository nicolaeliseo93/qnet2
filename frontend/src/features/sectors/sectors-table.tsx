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
import { sectorColumnRenderers } from '@/features/sectors/column-renderers'
import { deleteSector, fetchSector } from '@/features/sectors/api'
import { sectorKeys } from '@/features/sectors/query-keys'
import { SectorForm } from '@/features/sectors/sector-form'
import { SectorDetailView } from '@/features/sectors/sector-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { SectorDetail } from '@/features/sectors/types'

/** Domain key used to mount the generic table for sectors. */
const SECTORS_DOMAIN = 'sectors'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin Sectors adapter over the generic table. It mounts `<TableView>`
 * with the `sectors` domain, its custom cell renderers and a row-action
 * handler, and owns the CRUD flows: opening a Sheet for view/edit/create,
 * confirming + running the delete mutation (surfacing the backend's
 * restrictive-delete 409 when a sector still has children), and refreshing
 * both the SSRM grid and the parent-picker tree after every mutation.
 */
export function SectorsTable() {
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
        await deleteSector(row.id)
        toast.success(t('sectors.form.deleted'))
        refreshGrid()
        void queryClient.invalidateQueries({ queryKey: sectorKeys.tree })
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('sectors.form.deleteForbidden'))
        } else if (status === 409) {
          toast.error(t('sectors.form.deleteInUse'))
        } else {
          toast.error(t('sectors.form.deleteError'))
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
    (sector: SectorDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.setQueryData(sectorKeys.detail(sector.id), sector)
      void queryClient.invalidateQueries({ queryKey: sectorKeys.tree })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="sectors.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('sectors.form.newSector')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={SECTORS_DOMAIN}
        renderers={sectorColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('sectors.detail.title')}</SheetTitle>
                <SheetDescription>{t('sectors.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewSectorLoader sectorId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('sectors.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('sectors.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <SectorForm
                mode={{ type: 'create', parentId: null }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('sectors.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('sectors.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditSectorLoader
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

interface ViewSectorLoaderProps {
  sectorId: number
}

/**
 * Fetches the fresh sector detail and hands it down to the (presentational)
 * `SectorDetailView`, which owns no data-fetching state of its own.
 */
function ViewSectorLoader({ sectorId }: ViewSectorLoaderProps) {
  const { t } = useTranslation()
  const {
    data: sector,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(sectorKeys.detail(sectorId), () => fetchSector(sectorId))

  if (isError) {
    return (
      <DetailError
        message={t('sectors.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !sector) {
    return <DetailLoading />
  }

  return <SectorDetailView sector={sector} />
}

interface EditSectorLoaderProps {
  sectorId: number
  onSuccess: (sector: SectorDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized sector detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than
 * the grid row snapshot.
 */
function EditSectorLoader({ sectorId, onSuccess, onCancel }: EditSectorLoaderProps) {
  const { t } = useTranslation()
  const {
    data: sector,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(sectorKeys.detail(sectorId), () => fetchSector(sectorId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('sectors.detail.loadError')}</p>
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

  return <SectorForm mode={{ type: 'edit', sector }} onSuccess={onSuccess} onCancel={onCancel} />
}
