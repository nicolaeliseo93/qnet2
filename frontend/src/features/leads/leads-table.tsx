import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { ArrowRightLeft, FileUp, Plus } from 'lucide-react'
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
import { leadColumnRenderers } from '@/features/leads/column-renderers'
import { deleteLead } from '@/features/leads/api'
import { OPPORTUNITIES_DOMAIN } from '@/features/opportunities/api'

/** Domain key used to mount the generic table for leads. */
const LEADS_DOMAIN = 'leads'

/**
 * Domain icon override for the 'convert_to_opportunity' row action (spec
 * 0044): the backend action catalog fixes the icon key as 'arrow-right-left',
 * absent from the shared defaults in `action-icon-map.ts`. Hoisted at module
 * level (not inline in JSX) so its identity stays stable across renders — it
 * flows into `TableView`'s internal `useMemo` dependency list.
 */
const LEADS_ACTION_ICONS: ActionIconMap = { 'arrow-right-left': ArrowRightLeft }

/**
 * Thin Leads adapter over the generic table. It mounts `<TableView>` with the
 * `leads` domain, its custom cell renderers and a row-action handler, and
 * delegates the open mode (modal Sheet vs dedicated page) of view/edit/create
 * to `useModuleOpener`, resolved from the user's preference (spec 0042). It
 * still owns the delete flow (confirm + toast + grid refresh), the "Import"
 * navigation, and refreshes the SSRM grid after every mutation. Permission
 * gating is an affordance only; the backend re-authorizes each call.
 */
export function LeadsTable() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const stats = useStatsPanel(LEADS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(LEADS_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query is
  // invalidated inside `LeadFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(LEADS_DOMAIN, { onSaved })

  // Second, domain-generic opener mounting the OPPORTUNITIES form (spec
  // 0045): 'convert_to_opportunity' seeds it with the lead id and follows the
  // user's opportunities open mode, instead of always navigating to a page.
  // Reuses the same `onSaved` (grid refresh + stats invalidation) as the
  // leads opener above, since AC-024 requires the leads grid to reflect the
  // conversion (the converted row no longer exposes the action).
  const { openCreateWith: openOpportunityWith, sheet: opportunitySheet } = useModuleOpener(
    OPPORTUNITIES_DOMAIN,
    { onSaved },
  )

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
        case 'convert_to_opportunity':
          openOpportunityWith({ lead_id: row.id })
          break
        default:
          break
      }
    },
    [openView, openEdit, runDelete, openOpportunityWith],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

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
            <Can permission="leads.import">
              <Button variant="outline" className="bg-white" onClick={() => void navigate('/imports')}>
                <FileUp aria-hidden="true" />
                {t('leads.form.importLeads')}
              </Button>
            </Can>
            <Can permission="leads.create">
              <Button onClick={openCreate}>
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
        iconMap={LEADS_ACTION_ICONS}
      />

      {sheet}
      {opportunitySheet}

      <ResourceActivityDialog
        resource={LEADS_DOMAIN}
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
