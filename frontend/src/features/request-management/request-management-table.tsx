import { useCallback } from 'react'
import { PageHeader } from '@/components/page-header'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { requestManagementColumnRenderers } from '@/features/request-management/column-renderers'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'

/**
 * Thin Request Management adapter over the generic table (spec 0049): an
 * OPERATIVE view over the same Opportunity rows (D-1, no new entity, no
 * duplication). Mounts `<TableView>` with the `request-management` domain
 * and its status-badge renderers, and delegates the single "Lavora" row
 * action (`view`) to `useModuleOpener`, resolved from the user's open-mode
 * preference (spec 0042): modal mounts the work panel in a Sheet, page mode
 * navigates to `/request-management/:id`. No create/edit/delete affordance —
 * the record IS the Opportunity (D-9/D-10): CRUD stays on `opportunities.*`,
 * never this module's own permission set.
 */
export function RequestManagementTable() {
  const { openView, sheet } = useModuleOpener(REQUEST_MANAGEMENT_DOMAIN)

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      if (action.key === 'view') {
        openView(row)
      }
    },
    [openView],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />

      <TableView
        domain={REQUEST_MANAGEMENT_DOMAIN}
        renderers={requestManagementColumnRenderers}
        onAction={handleAction}
      />

      {sheet}
    </div>
  )
}
