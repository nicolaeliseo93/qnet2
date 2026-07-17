import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { ApiErrorResponse } from '@/api/types'
import { leadStatusColumnRenderers } from '@/features/lead-statuses/column-renderers'
import { deleteLeadStatus } from '@/features/lead-statuses/api'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/** Domain key used to mount the generic table for lead statuses. */
const LEAD_STATUSES_DOMAIN = 'lead-statuses'

/**
 * Thin Lead Statuses adapter over the generic table. It mounts `<TableView>`
 * with the `lead-statuses` domain, its custom cell renderers and a
 * row-action handler, and delegates the open mode (modal Sheet vs dedicated
 * page) of view/edit/create to `useModuleOpener`, resolved from the user's
 * preference (spec 0042). It still owns the delete flow (confirming +
 * running the delete mutation, surfacing the backend's exact 409 message
 * when the status is still referenced by a Lead, BR-3) and refreshing the
 * SSRM grid after every mutation via the table's imperative handle.
 * Permission gating is an affordance only; the backend re-authorizes each
 * call.
 */
export function LeadStatusesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(LEAD_STATUSES_DOMAIN, {
    onSaved: refreshGrid,
  })

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
        default:
          break
      }
    },
    [openView, openEdit, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatusReorderToggle
              resource={LEAD_STATUSES_DOMAIN}
              permission="lead-statuses.update"
              labels={{
                openButton: t('leadStatuses.reorder.openButton'),
                title: t('leadStatuses.reorder.title'),
                subtitle: t('leadStatuses.reorder.subtitle'),
                dragHandleLabel: t('leadStatuses.reorder.dragHandleLabel'),
                loadError: t('leadStatuses.reorder.loadError'),
                saved: t('leadStatuses.reorder.saved'),
                forbidden: t('leadStatuses.reorder.forbidden'),
                genericError: t('leadStatuses.reorder.genericError'),
              }}
              onReordered={refreshGrid}
            />
            <Can permission="lead-statuses.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('leadStatuses.form.newLeadStatus')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={LEAD_STATUSES_DOMAIN}
        renderers={leadStatusColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={LEAD_STATUSES_DOMAIN}
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
