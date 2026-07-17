import { useTranslation } from 'react-i18next'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import { UserCheck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { ImportRowResolution, ImportRunRowItem } from '@/features/imports/wizard/types'

const RESOLUTION_OPTIONS: ImportRowResolution[] = ['skip', 'create', 'update']

export interface ReviewResolutionCellParams extends ICellRendererParams<ImportRunRowItem, string | null> {
  /** Fired when the operator picks a resolution; absent in `readOnly` mode. */
  onResolve?: (row: ImportRunRowItem, resolution: ImportRowResolution, node: IRowNode<ImportRunRowItem>) => void
  readOnly?: boolean
}

/**
 * Duplicate-row resolution cell (spec 0036 AC-008): shows the matched
 * anagrafica's name — with a badge when that anagrafica already has a lead
 * on the run's campaign (`duplicate_meta.lead_id`) — and a compact
 * skip/create/update select. Non-`duplicate` rows (or a `duplicate` row
 * whose match was cleared by a later edit) render an em dash, mirroring
 * `ReviewMessagesCell`'s empty state.
 */
export function ReviewResolutionCell({ data, node, onResolve, readOnly }: ReviewResolutionCellParams) {
  const { t } = useTranslation('importWizard')

  if (!data || data.status !== 'duplicate' || !data.duplicate_meta) {
    return <span className="text-muted-foreground">—</span>
  }

  const meta = data.duplicate_meta

  return (
    <div className="flex h-full items-center gap-1.5 overflow-hidden">
      <span className="min-w-0 flex-1 truncate text-xs" title={meta.registry_name}>
        {meta.registry_name}
      </span>
      {meta.lead_id != null ? (
        <Badge
          variant="outline"
          className="shrink-0 gap-1 border-sky-500 px-1.5 text-sky-700 dark:text-sky-400"
          title={t('review.resolution.leadInCampaign')}
        >
          <UserCheck className="size-3" aria-hidden="true" />
        </Badge>
      ) : null}
      <Select
        value={data.resolution ?? undefined}
        onValueChange={(value) => onResolve?.(data, value as ImportRowResolution, node)}
        disabled={readOnly}
      >
        <SelectTrigger size="sm" className="h-7 w-28 shrink-0 text-xs" aria-label={t('review.resolution.label')}>
          <SelectValue placeholder={t('review.resolution.placeholder')} />
        </SelectTrigger>
        <SelectContent>
          {RESOLUTION_OPTIONS.map((option) => (
            <SelectItem key={option} value={option}>
              {t(`review.resolution.options.${option}`)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}
