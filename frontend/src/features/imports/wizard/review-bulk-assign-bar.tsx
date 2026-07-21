import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { UserCheck } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'

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

export interface ReviewBulkAssignInput {
  operatorId: number | null
  siteId: number | null
}

export interface ReviewBulkAssignBarProps {
  selection: ReviewBulkSelectionState
  /** PATCHes the combined bulk assignment; rejects (already toasted by the caller) on failure. */
  onAssign: (input: ReviewBulkAssignInput) => Promise<void>
}

function resolveSelectionLabel(selection: ReviewBulkSelectionState, t: TFunction): string {
  if (selection.selectAll) {
    return selection.toggledNodes.length > 0
      ? t('review.bulkAssign.allExcept', { count: selection.toggledNodes.length })
      : t('review.bulkAssign.all')
  }
  return t('review.bulkAssign.count', { count: selection.toggledNodes.length })
}

/**
 * Compact toolbar shown above the review grid only while the SSRM selection
 * is non-empty: selection count (or "All"), an operator picker, a site
 * picker and a single "Assign" action applying whichever field(s) are set.
 * Assign-only — clearing an override is a single-row cell concern
 * (`ReviewOperatorCell`/`ReviewSiteCell`), never bulk.
 */
export function ReviewBulkAssignBar({ selection, onAssign }: ReviewBulkAssignBarProps) {
  const { t } = useTranslation('importWizard')
  const [operatorId, setOperatorId] = useState<number | null>(null)
  const [siteId, setSiteId] = useState<number | null>(null)
  const [isAssigning, setIsAssigning] = useState(false)

  function handleAssign() {
    if (operatorId === null && siteId === null) return
    setIsAssigning(true)
    onAssign({ operatorId, siteId })
      .then(() => {
        setOperatorId(null)
        setSiteId(null)
      })
      .catch(() => {
        // Already surfaced via toast by the caller; keep the picks so the
        // operator can retry without reselecting.
      })
      .finally(() => setIsAssigning(false))
  }

  return (
    <div
      className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 px-2.5 py-1.5 text-xs"
      role="toolbar"
      aria-label={t('review.bulkAssign.toolbarLabel')}
    >
      <span className="font-medium">{resolveSelectionLabel(selection, t)}</span>
      <div className="min-w-40 flex-1 sm:flex-none">
        <AsyncPaginatedSelect
          resource={USERS_FOR_SELECT_RESOURCE}
          value={operatorId}
          onChange={setOperatorId}
          disabled={isAssigning}
          showAvatar
          className="h-7 text-xs"
          labels={{
            placeholder: t('review.bulkAssign.placeholder'),
            searchPlaceholder: t('review.operator.searchPlaceholder'),
            empty: t('review.operator.empty'),
            error: t('review.operator.selectError'),
            clearLabel: t('review.operator.selectClear'),
            triggerLabel: t('review.bulkAssign.placeholder'),
            retry: t('review.operator.retry'),
          }}
        />
      </div>
      <div className="min-w-40 flex-1 sm:flex-none">
        <AsyncPaginatedSelect
          resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
          value={siteId}
          onChange={setSiteId}
          disabled={isAssigning}
          className="h-7 text-xs"
          labels={{
            placeholder: t('review.bulkAssign.sitePlaceholder'),
            searchPlaceholder: t('review.site.searchPlaceholder'),
            empty: t('review.site.empty'),
            error: t('review.site.selectError'),
            clearLabel: t('review.site.selectClear'),
            triggerLabel: t('review.bulkAssign.sitePlaceholder'),
            retry: t('review.site.retry'),
          }}
        />
      </div>
      <Button
        type="button"
        size="sm"
        className="h-7 gap-1.5 px-2.5 text-xs"
        onClick={handleAssign}
        disabled={(operatorId === null && siteId === null) || isAssigning}
      >
        <UserCheck className="size-3.5" aria-hidden="true" />
        {t('review.bulkAssign.assign')}
      </Button>
    </div>
  )
}
