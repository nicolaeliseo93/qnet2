import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { Plus, Upload } from 'lucide-react'
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
import { DropdownMenuItem } from '@/components/ui/dropdown-menu'
import { Can } from '@/features/auth/can'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type {
  TableActionDefinition,
  TableRow,
} from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { operationalSiteColumnRenderers } from '@/features/operational-sites/column-renderers'
import { deleteOperationalSite, fetchOperationalSite } from '@/features/operational-sites/api'
import { OperationalSiteForm } from '@/features/operational-sites/operational-site-form'
import { OperationalSiteDetailView } from '@/features/operational-sites/operational-site-detail'
import type { OperationalSiteDetail } from '@/features/operational-sites/types'
import { ImportDialog } from '@/features/imports/import-dialog'

/** Domain key used to mount the generic table for operational sites. */
const OPERATIONAL_SITES_DOMAIN = 'operational-sites'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single operational site's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['operational-sites', 'detail', id] as const
}

/**
 * Thin Operational Sites adapter over the generic table. It mounts
 * `<TableView>` with the `operational-sites` domain, its custom cell
 * renderers and a row-action handler, and owns the CRUD flows: opening a
 * Sheet for view/edit/create, confirming + running the delete mutation, and
 * refreshing the SSRM grid after every mutation via the table's imperative
 * handle. No table logic lives here — only operational-sites CRUD wiring.
 * Permission gating is an affordance only; the backend re-authorizes each call.
 */
export function OperationalSitesTable() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [importOpen, setImportOpen] = useState(false)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteOperationalSite(row.id)
        toast.success(t('operationalSites.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('operationalSites.form.deleteForbidden')
            : t('operationalSites.form.deleteError'),
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
    (operationalSite: OperationalSiteDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(operationalSite.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="operational-sites.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('operationalSites.form.newOperationalSite')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={OPERATIONAL_SITES_DOMAIN}
        renderers={operationalSiteColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        importSlot={
          <Can permission="operational-sites.import">
            <DropdownMenuItem
              onSelect={(event) => {
                event.preventDefault()
                setImportOpen(true)
              }}
            >
              <Upload aria-hidden="true" />
              {t('imports.action')}
            </DropdownMenuItem>
          </Can>
        }
      />

      <ImportDialog
        domain={OPERATIONAL_SITES_DOMAIN}
        resource={OPERATIONAL_SITES_DOMAIN}
        open={importOpen}
        onOpenChange={setImportOpen}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('operationalSites.detail.title')}</SheetTitle>
                <SheetDescription>{t('operationalSites.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewOperationalSiteLoader operationalSiteId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('operationalSites.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('operationalSites.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <OperationalSiteForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('operationalSites.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('operationalSites.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditOperationalSiteLoader
                operationalSiteId={sheet.row.id}
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

interface ViewOperationalSiteLoaderProps {
  operationalSiteId: number
}

/**
 * Fetches the fresh operational site detail and hands it down to the
 * (presentational) `OperationalSiteDetailView`, which owns no data-fetching
 * state of its own.
 */
function ViewOperationalSiteLoader({ operationalSiteId }: ViewOperationalSiteLoaderProps) {
  const { t } = useTranslation()
  const {
    data: operationalSite,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(operationalSiteId), () => fetchOperationalSite(operationalSiteId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('operationalSites.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !operationalSite) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <OperationalSiteDetailView operationalSite={operationalSite} />
}

interface EditOperationalSiteLoaderProps {
  operationalSiteId: number
  onSuccess: (operationalSite: OperationalSiteDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized operational site detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values rather
 * than the grid row snapshot.
 */
function EditOperationalSiteLoader({
  operationalSiteId,
  onSuccess,
  onCancel,
}: EditOperationalSiteLoaderProps) {
  const { t } = useTranslation()
  const {
    data: operationalSite,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(operationalSiteId), () => fetchOperationalSite(operationalSiteId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('operationalSites.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !operationalSite) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <OperationalSiteForm
      mode={{ type: 'edit', operationalSite }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}
