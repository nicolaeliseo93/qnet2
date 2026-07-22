import { useCallback, useRef, useState } from 'react'
import { MessageSquare, Paperclip } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { DocumentsDialog } from '@/features/attachments/documents-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { NotesDialog } from '@/features/notes/notes-dialog'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import { requestManagementColumnRenderers } from '@/features/request-management/column-renderers'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'

/**
 * Domain icon overrides for the `documents`/`notes` row actions: the backend
 * action catalog fixes their icon keys as 'paperclip'/'message-square',
 * absent from the shared defaults in `action-icon-map.ts`. Hoisted at module
 * level (not inline in JSX) so its identity stays stable across renders.
 */
const REQUEST_MANAGEMENT_ACTION_ICONS: ActionIconMap = {
  paperclip: Paperclip,
  'message-square': MessageSquare,
}

/**
 * Thin Request Management adapter over the generic table (spec 0049): an
 * OPERATIVE view over the same Opportunity rows (D-1, no new entity, no
 * duplication). Mounts `<TableView>` with the `request-management` domain
 * and its status-badge renderers, and delegates the "Lavora" row action
 * (`view`) to `useModuleOpener`, resolved from the user's open-mode
 * preference (spec 0042): modal mounts the work panel in a Sheet, page mode
 * navigates to `/request-management/:id`. The `documents` row action opens the
 * shared `DocumentsDialog` on the same polymorphic owner the opportunities
 * module uses (the row IS the Opportunity), gated server-side by this module's
 * OWN `request-management.viewDocuments` (D-2). The `notes` row action opens
 * the agnostic `NotesDialog` on the same row (spec 0052), gated server-side by
 * the notes feature's own hybrid authorization (D-6) — this module only wires
 * the `entityType`/`entityId` pair. No create/edit/delete affordance — CRUD
 * stays on `opportunities.*`, never this permission set.
 */
export function RequestManagementTable() {
  const { openView, sheet } = useModuleOpener(REQUEST_MANAGEMENT_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const [documentsRowId, setDocumentsRowId] = useState<number | null>(null)
  const [notesRowId, setNotesRowId] = useState<number | null>(null)

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'documents':
          setDocumentsRowId(row.id)
          break
        case 'notes':
          setNotesRowId(row.id)
          break
        default:
          break
      }
    },
    [openView],
  )

  // Documents are edited from inside the dialog (upload/delete); refresh the
  // grid on close so the row's `documents_count` badge reflects the change.
  const handleDocumentsOpenChange = useCallback((open: boolean) => {
    if (!open) {
      setDocumentsRowId(null)
      tableRef.current?.refresh()
    }
  }, [])

  // Notes are added/deleted from inside the dialog; refresh the grid on close
  // so the row's `notes_count` badge reflects the change (mirrors documents).
  const handleNotesOpenChange = useCallback((open: boolean) => {
    if (!open) {
      setNotesRowId(null)
      tableRef.current?.refresh()
    }
  }, [])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />

      <TableView
        ref={tableRef}
        domain={REQUEST_MANAGEMENT_DOMAIN}
        renderers={requestManagementColumnRenderers}
        onAction={handleAction}
        iconMap={REQUEST_MANAGEMENT_ACTION_ICONS}
      />

      {sheet}

      <DocumentsDialog
        resource={OPPORTUNITY_ATTACHABLE_ALIAS}
        id={documentsRowId}
        onOpenChange={handleDocumentsOpenChange}
      />

      <NotesDialog
        entityType={REQUEST_MANAGEMENT_DOMAIN}
        entityId={notesRowId}
        onOpenChange={handleNotesOpenChange}
      />
    </div>
  )
}
