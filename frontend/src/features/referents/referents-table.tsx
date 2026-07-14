import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { referentColumnRenderers } from '@/features/referents/column-renderers'
import { deleteReferent } from '@/features/referents/api'

/** Domain key used to mount the generic table for referents. */
const REFERENTS_DOMAIN = 'referents'

/**
 * Thin Referents adapter over the generic table. It mounts `<TableView>` with
 * the `referents` domain, its custom cell renderers and a row-action handler.
 * View/create/edit are dedicated pages (spec 0022): the row actions and the
 * "New" button navigate there. Only the delete stays here — it runs inline and
 * refreshes the SSRM grid through the table's imperative handle. Permission
 * gating is an affordance only; the backend re-authorizes each call.
 */
export function ReferentsTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(REFERENTS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(REFERENTS_DOMAIN)
  const navigate = useNavigate()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteReferent(row.id)
        toast.success(t('referents.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403 ? t('referents.form.deleteForbidden') : t('referents.form.deleteError'),
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
          void navigate(`/referents/${row.id}`)
          break
        case 'edit':
          void navigate(`/referents/${row.id}/edit`)
          break
        case 'delete':
          void runDelete(row)
          break
        default:
          break
      }
    },
    [navigate, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={REFERENTS_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="referents.create">
              <Button onClick={() => void navigate('/referents/new')}>
                <Plus aria-hidden="true" />
                {t('referents.form.newReferent')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={REFERENTS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={REFERENTS_DOMAIN}
        renderers={referentColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />
    </div>
  )
}
