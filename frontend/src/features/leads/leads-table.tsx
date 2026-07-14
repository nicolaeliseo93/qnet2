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
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { leadColumnRenderers } from '@/features/leads/column-renderers'
import { deleteLead, fetchLead, leadDetailQueryKey } from '@/features/leads/api'
import { LeadForm } from '@/features/leads/lead-form'
import { LeadDetailView } from '@/features/leads/lead-detail'
import type { LeadDetail } from '@/features/leads/types'

/** Domain key used to mount the generic table for leads. */
const LEADS_DOMAIN = 'leads'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin Leads adapter over the generic table. It mounts `<TableView>` with the
 * `leads` domain, its custom cell renderers and a row-action handler, and
 * owns the CRUD flows: opening a Sheet for view/edit/create, confirming +
 * running the delete mutation, and refreshing the SSRM grid after every
 * mutation via the table's imperative handle (spec 0025 Parte B — the
 * dedicated pages remain as deep-links, this adapter only changes the mount
 * point). Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function LeadsTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(LEADS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(LEADS_DOMAIN)
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
        await deleteLead(row.id)
        toast.success(t('leads.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('leads.form.deleteForbidden'))
        } else {
          toast.error(t('leads.form.deleteError'))
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
    (lead: LeadDetail) => {
      closeSheet()
      refreshGrid()
      invalidateStats()
      queryClient.invalidateQueries({ queryKey: leadDetailQueryKey(lead.id) })
    },
    [closeSheet, refreshGrid, queryClient, invalidateStats],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={LEADS_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="leads.create">
              <Button onClick={() => setSheet({ kind: 'create' })}>
                <Plus aria-hidden="true" />
                {t('leads.form.newLead')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={LEADS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={LEADS_DOMAIN}
        renderers={leadColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${LEADS_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('leads.detail.title')}</SheetTitle>
                <SheetDescription>{t('leads.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewLeadLoader leadId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('leads.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('leads.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <LeadForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('leads.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('leads.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditLeadLoader
                leadId={sheet.row.id}
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

interface ViewLeadLoaderProps {
  leadId: number
}

/**
 * Fetches the fresh lead detail and hands it down to the (presentational)
 * `LeadDetailView`, which owns no data-fetching state of its own.
 */
function ViewLeadLoader({ leadId }: ViewLeadLoaderProps) {
  const { t } = useTranslation()
  const {
    data: lead,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(leadDetailQueryKey(leadId), () => fetchLead(leadId))

  if (isError) {
    return (
      <DetailError
        message={t('leads.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !lead) {
    return <DetailLoading />
  }

  return <LeadDetailView lead={lead} />
}

interface EditLeadLoaderProps {
  leadId: number
  onSuccess: (lead: LeadDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized lead detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row snapshot.
 */
function EditLeadLoader({ leadId, onSuccess, onCancel }: EditLeadLoaderProps) {
  const { t } = useTranslation()
  const {
    data: lead,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(leadDetailQueryKey(leadId), () => fetchLead(leadId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('leads.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !lead) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <LeadForm mode={{ type: 'edit', lead }} onSuccess={onSuccess} onCancel={onCancel} />
}
