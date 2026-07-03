import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { downloadImportErrorReport } from '@/features/imports/api'

interface ImportErrorReportLinkProps {
  domain: string
  importRunId: number
}

/**
 * Downloads the full CSV error report for a run (shown whenever the run's
 * `has_error_report` flag is set — awaiting_confirmation, completed, failed).
 */
export function ImportErrorReportLink({ domain, importRunId }: ImportErrorReportLinkProps) {
  const { t } = useTranslation()
  const [isDownloading, setIsDownloading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleDownload = async () => {
    setIsDownloading(true)
    setError(null)
    try {
      await downloadImportErrorReport(domain, importRunId)
    } catch {
      setError(t('imports.errors.reportDownloadError'))
    } finally {
      setIsDownloading(false)
    }
  }

  return (
    <div className="flex flex-col items-start gap-1">
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={isDownloading}
        onClick={() => void handleDownload()}
      >
        <Download aria-hidden="true" />
        {t('imports.buttons.downloadErrorReport')}
      </Button>
      {error ? (
        <span role="alert" className="text-xs text-destructive">
          {error}
        </span>
      ) : null}
    </div>
  )
}
