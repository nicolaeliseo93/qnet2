import { useTranslation } from 'react-i18next'
import type { VariantProps } from 'class-variance-authority'
import { Download } from 'lucide-react'
import { Badge, type badgeVariants } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { ExportRun } from '@/features/exports/types'

type BadgeVariant = NonNullable<VariantProps<typeof badgeVariants>['variant']>

/** Badge tone per status; failed reads as destructive, completed as the positive default. */
const STATUS_BADGE_VARIANT: Record<ExportRun['status'], BadgeVariant> = {
  processing: 'secondary',
  completed: 'default',
  failed: 'destructive',
}

interface ExportProgressProps {
  exportRun: ExportRun
  onDownload: () => void
  isDownloading: boolean
  downloadError: string | null
  /** Called when the user dismisses a terminal (completed/failed) run. */
  onClose: () => void
}

/**
 * Status view shown while `processing` (polling in progress) and on the
 * terminal `completed`/`failed` outcome. Purely presentational: the poll
 * itself is driven by `useExport`.
 */
export function ExportProgress({
  exportRun,
  onDownload,
  isDownloading,
  downloadError,
  onClose,
}: ExportProgressProps) {
  const { t } = useTranslation()
  const isActive = exportRun.status === 'processing'
  const isTerminal = exportRun.status === 'completed' || exportRun.status === 'failed'

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <Badge variant={STATUS_BADGE_VARIANT[exportRun.status]}>
          {t(`exports.status.${exportRun.status}`)}
        </Badge>
        <span className="truncate text-sm text-muted-foreground">
          {exportRun.original_filename}
        </span>
      </div>

      <div
        role="progressbar"
        aria-valuetext={t(`exports.status.${exportRun.status}`)}
        className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
      >
        <div
          className={`h-full rounded-full ${
            exportRun.status === 'failed' ? 'bg-destructive' : 'bg-primary'
          } ${isActive ? 'w-1/2 animate-pulse' : 'w-full'}`}
        />
      </div>

      {exportRun.row_count != null ? (
        <p className="text-sm text-muted-foreground">
          {t('exports.rowCount', { count: exportRun.row_count })}
        </p>
      ) : null}

      {exportRun.status === 'failed' ? (
        <p className="text-sm text-destructive" role="alert">
          {t('exports.errors.jobFailed')}
        </p>
      ) : null}

      {downloadError ? (
        <p className="text-sm text-destructive" role="alert">
          {downloadError}
        </p>
      ) : null}

      {isTerminal ? (
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose}>
            {t('exports.buttons.close')}
          </Button>
          {exportRun.has_file ? (
            <Button type="button" onClick={onDownload} disabled={isDownloading}>
              <Download aria-hidden="true" />
              {isDownloading ? t('exports.buttons.downloading') : t('exports.buttons.download')}
            </Button>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
