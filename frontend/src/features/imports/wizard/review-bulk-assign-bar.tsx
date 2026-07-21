import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { UserCog } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  AssignOperatorsDialog,
  type AssignOperatorsDialogInput,
} from '@/features/leads/assign-operators-dialog'

/**
 * Selection shape consumed by the bar, mirroring AG Grid's own server-side
 * selection state (`gridApi.getServerSideSelectionState()`): `selectAll:
 * false` — `toggledNodes` are the selected (included) row ids; `selectAll:
 * true` — `toggledNodes` are the deselected (excluded) row ids.
 */
export interface ReviewBulkSelectionState {
  selectAll: boolean
  toggledNodes: string[]
}

export interface ReviewBulkAssignBarProps {
  selection: ReviewBulkSelectionState
  /**
   * Total staged rows in the run. The selection state above carries no total
   * of its own, so it is the only way to approximate a selection count while
   * `selectAll` is true (documented approximation, spec 0048 — the review
   * grid has no cheaper source of truth than the run's own row count).
   */
  totalRows: number
  /**
   * The Sede to precompile in the popup (spec 0048 AC-031), when the caller
   * could cheaply determine the current selection shares one — `null`
   * otherwise (mixed sites, any unset, or a `selectAll` selection).
   */
  defaultSiteId?: number | null
  /** PATCHes the combined bulk assignment; rejects (already toasted by the caller) on failure. */
  onAssign: (input: AssignOperatorsDialogInput) => Promise<void>
}

function resolveSelectionLabel(selection: ReviewBulkSelectionState, t: TFunction): string {
  if (selection.selectAll) {
    return selection.toggledNodes.length > 0
      ? t('review.bulkAssign.allExcept', { count: selection.toggledNodes.length })
      : t('review.bulkAssign.all')
  }
  return t('review.bulkAssign.count', { count: selection.toggledNodes.length })
}

/** See `ReviewBulkAssignBarProps.totalRows`. */
function resolveSelectionCount(selection: ReviewBulkSelectionState, totalRows: number): number {
  if (!selection.selectAll) {
    return selection.toggledNodes.length
  }
  return Math.max(totalRows - selection.toggledNodes.length, 0)
}

/**
 * Compact toolbar shown above the review grid only while the SSRM selection
 * is non-empty: the selection count (or "All") and a single trigger opening
 * the SAME "Assegna operatori" popup the Lead table uses (spec 0048 AC-050),
 * instead of duplicating a pair of operator/site pickers here.
 */
export function ReviewBulkAssignBar({ selection, totalRows, defaultSiteId, onAssign }: ReviewBulkAssignBarProps) {
  const { t } = useTranslation('importWizard')
  const [open, setOpen] = useState(false)

  return (
    <div
      className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 px-2.5 py-1.5 text-xs"
      role="toolbar"
      aria-label={t('review.bulkAssign.toolbarLabel')}
    >
      <span className="font-medium">{resolveSelectionLabel(selection, t)}</span>
      <Button
        type="button"
        size="sm"
        className="h-7 gap-1.5 px-2.5 text-xs"
        onClick={() => setOpen(true)}
      >
        <UserCog className="size-3.5" aria-hidden="true" />
        {t('review.bulkAssign.assign')}
      </Button>

      <AssignOperatorsDialog
        open={open}
        onOpenChange={setOpen}
        selectionCount={resolveSelectionCount(selection, totalRows)}
        defaultSiteId={defaultSiteId}
        onAssign={onAssign}
      />
    </div>
  )
}
