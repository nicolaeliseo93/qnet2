import { useState } from 'react'
import { ArrowUpDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { StatusReorderSheet, type StatusReorderSheetLabels } from '@/features/status-reorder/status-reorder-sheet'

interface StatusReorderToggleLabels extends StatusReorderSheetLabels {
  openButton: string
}

interface StatusReorderToggleProps {
  /** Resource segment of the endpoint: `pipeline-statuses` or `lead-statuses`. */
  resource: string
  /** Permission gating the toolbar button and the sheet (spec 0039: `{resource}.update`). */
  permission: string
  labels: StatusReorderToggleLabels
  /** Called after a successful reorder so the caller can refresh its own table view. */
  onReordered: () => void
}

/**
 * Toolbar affordance for the two status configurators (spec 0039 D-4): a
 * gated button that opens `<StatusReorderSheet>`, owning only the sheet's
 * open/closed state. Extracted out of the table adapters so both
 * `pipeline-statuses-table.tsx` and `lead-statuses-table.tsx` stay within the
 * engineering size limits (`engineering.md` §6) instead of duplicating this
 * wiring inline.
 */
export function StatusReorderToggle({ resource, permission, labels, onReordered }: StatusReorderToggleProps) {
  const [open, setOpen] = useState(false)

  return (
    <Can permission={permission}>
      <Button variant="outline" onClick={() => setOpen(true)}>
        <ArrowUpDown aria-hidden="true" />
        {labels.openButton}
      </Button>
      <StatusReorderSheet
        open={open}
        onOpenChange={setOpen}
        resource={resource}
        labels={labels}
        onReordered={onReordered}
      />
    </Can>
  )
}
