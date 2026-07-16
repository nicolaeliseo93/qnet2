import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
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
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { companySiteColumnRenderers } from '@/features/company-sites/column-renderers'
import { deleteCompanySite, fetchCompanySite } from '@/features/company-sites/api'
import { CompanySiteForm } from '@/features/company-sites/company-site-form'
import { CompanySiteDetailView } from '@/features/company-sites/company-site-detail'

/** Domain key used to mount the generic table for company sites. */
const COMPANY_SITES_DOMAIN = 'company-sites'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin Company Sites adapter over the generic table. It mounts `<TableView>`
 * with the `company-sites` domain, its custom cell renderers and a row-action
 * handler, and owns the CRUD flows: opening a Sheet for view/edit/create,
 * confirming + running the delete mutation, and refreshing the SSRM grid
 * after every mutation (including a set-default from the view sheet) via the
 * table's imperative handle. No table logic lives here — only company-sites
 * CRUD wiring. Permission gating is an affordance only; the backend
 * re-authorizes each call.
 */
export function CompanySitesTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(COMPANY_SITES_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(COMPANY_SITES_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteCompanySite(row.id)
        toast.success(t('companySites.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('companySites.form.deleteForbidden')
            : t('companySites.form.deleteError'),
        )
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, invalidateStats],
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

  const onMutationSuccess = useCallback(() => {
    closeSheet()
    refreshGrid()
    invalidateStats()
  }, [closeSheet, refreshGrid, invalidateStats])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={COMPANY_SITES_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="company-sites.create">
              <Button onClick={() => setSheet({ kind: 'create' })}>
                <Plus aria-hidden="true" />
                {t('companySites.form.newCompanySite')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={COMPANY_SITES_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={COMPANY_SITES_DOMAIN}
        renderers={companySiteColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${COMPANY_SITES_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('companySites.detail.title')}</SheetTitle>
                <SheetDescription>{t('companySites.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <CompanySiteDetailView
                companySiteId={sheet.row.id}
                onDefaultChange={refreshGrid}
              />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('companySites.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('companySites.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <CompanySiteForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('companySites.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('companySites.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditCompanySiteLoader
                companySiteId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
                onSiteChange={refreshGrid}
              />
            </>
          )}
        </SheetContent>
      </Sheet>
    </div>
  )
}

interface EditCompanySiteLoaderProps {
  companySiteId: number
  onSuccess: () => void
  onCancel: () => void
  onSiteChange: () => void
}

/**
 * Fetches the fresh, re-authorized company-site detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than the grid row snapshot.
 */
function EditCompanySiteLoader({
  companySiteId,
  onSuccess,
  onCancel,
  onSiteChange,
}: EditCompanySiteLoaderProps) {
  const { t } = useTranslation()
  const {
    data: companySite,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['company-sites', 'detail', companySiteId], () =>
    fetchCompanySite(companySiteId),
  )

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('companySites.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !companySite) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <CompanySiteForm
      mode={{ type: 'edit', companySite }}
      onSuccess={onSuccess}
      onCancel={onCancel}
      onSiteChange={onSiteChange}
    />
  )
}
