import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Paperclip, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { opportunityColumnRenderers } from '@/features/opportunities/column-renderers'
import { OpportunityDocumentsDialog } from '@/features/opportunities/opportunity-documents-dialog'
import { deleteOpportunity, OPPORTUNITIES_DOMAIN } from '@/features/opportunities/api'

/**
 * Domain icon override for the 'documents' row action (spec: attachments row
 * action): the backend action catalog fixes the icon key as 'paperclip',
 * absent from the shared defaults in `action-icon-map.ts`. Hoisted at module
 * level (not inline in JSX), mirroring `LEADS_ACTION_ICONS`, so its identity
 * stays stable across renders.
 */
const OPPORTUNITIES_ACTION_ICONS: ActionIconMap = { paperclip: Paperclip }

/**
 * Thin Opportunities adapter over the generic table (spec 0040, mirrors
 * Leads). It mounts `<TableView>` with the `opportunities` domain, its custom
 * cell renderers and a row-action handler, and delegates the open mode (modal
 * Sheet vs dedicated page) of view/edit/create to `useModuleOpener`, resolved
 * from the user's preference (spec 0042). It still owns the delete flow
 * (confirm + toast + grid refresh) and refreshes the SSRM grid after every
 * mutation. Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function OpportunitiesTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(OPPORTUNITIES_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(OPPORTUNITIES_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [documentsRowId, setDocumentsRowId] = useState<number | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query is
  // invalidated inside `OpportunityFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(OPPORTUNITIES_DOMAIN, { onSaved })

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
          openView(row)
          break
        case 'edit':
          openEdit(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        case 'documents':
          setDocumentsRowId(row.id)
          break
        default:
          break
      }
    },
    [openView, openEdit, runDelete],
  )

  // Documents are edited from inside the dialog (upload/delete); refresh the
  // grid on close so the row's `documents_count` badge reflects the change.
  const handleDocumentsOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        setDocumentsRowId(null)
        refreshGrid()
      }
    },
    [refreshGrid],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

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
              <Button onClick={openCreate}>
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
        iconMap={OPPORTUNITIES_ACTION_ICONS}
      />

      {sheet}

      <ResourceActivityDialog
        resource={OPPORTUNITIES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />

      <OpportunityDocumentsDialog
        opportunityId={documentsRowId}
        onOpenChange={handleDocumentsOpenChange}
      />
    </div>
  )
}
