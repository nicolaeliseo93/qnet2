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
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { opportunityColumnRenderers } from '@/features/opportunities/column-renderers'
import {
  deleteOpportunity,
  fetchOpportunity,
  OPPORTUNITIES_DOMAIN,
  opportunityDetailQueryKey,
} from '@/features/opportunities/api'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import { OpportunityDetailView } from '@/features/opportunities/opportunity-detail'
import type { OpportunityDetail } from '@/features/opportunities/types'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin Opportunities adapter over the generic table (spec 0040, mirrors
 * Leads). It mounts `<TableView>` with the `opportunities` domain, its custom
 * cell renderers and a row-action handler, and owns the CRUD flows: opening a
 * Sheet for view/edit/create, confirming + running the delete mutation, and
 * refreshing the SSRM grid after every mutation via the table's imperative
 * handle. Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function OpportunitiesTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(OPPORTUNITIES_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(OPPORTUNITIES_DOMAIN)
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
        await deleteOpportunity(row.id)
        toast.success(t('opportunities.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('opportunities.form.deleteForbidden'))
        } else {
          toast.error(t('opportunities.form.deleteError'))
        }
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
    (opportunity: OpportunityDetail) => {
      closeSheet()
      refreshGrid()
      invalidateStats()
      queryClient.invalidateQueries({ queryKey: opportunityDetailQueryKey(opportunity.id) })
    },
    [closeSheet, refreshGrid, queryClient, invalidateStats],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={OPPORTUNITIES_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="opportunities.create">
              <Button onClick={() => setSheet({ kind: 'create' })}>
                <Plus aria-hidden="true" />
                {t('opportunities.form.newOpportunity')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={OPPORTUNITIES_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={OPPORTUNITIES_DOMAIN}
        renderers={opportunityColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${OPPORTUNITIES_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('opportunities.detail.title')}</SheetTitle>
                <SheetDescription>{t('opportunities.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewOpportunityLoader opportunityId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('opportunities.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('opportunities.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <OpportunityForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('opportunities.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('opportunities.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditOpportunityLoader
                opportunityId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>

      <ResourceActivityDialog
        resource={OPPORTUNITIES_DOMAIN}
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

interface ViewOpportunityLoaderProps {
  opportunityId: number
}

/**
 * Fetches the fresh opportunity detail and hands it down to the
 * (presentational) `OpportunityDetailView`, which owns no data-fetching state
 * of its own.
 */
function ViewOpportunityLoader({ opportunityId }: ViewOpportunityLoaderProps) {
  const { t } = useTranslation()
  const {
    data: opportunity,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(opportunityDetailQueryKey(opportunityId), () => fetchOpportunity(opportunityId))

  if (isError) {
    return (
      <DetailError
        message={t('opportunities.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !opportunity) {
    return <DetailLoading />
  }

  return <OpportunityDetailView opportunity={opportunity} />
}

interface EditOpportunityLoaderProps {
  opportunityId: number
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized opportunity detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than the grid row snapshot.
 */
function EditOpportunityLoader({ opportunityId, onSuccess, onCancel }: EditOpportunityLoaderProps) {
  const { t } = useTranslation()
  const {
    data: opportunity,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(opportunityDetailQueryKey(opportunityId), () => fetchOpportunity(opportunityId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('opportunities.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !opportunity) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <OpportunityForm mode={{ type: 'edit', opportunity }} onSuccess={onSuccess} onCancel={onCancel} />
}
