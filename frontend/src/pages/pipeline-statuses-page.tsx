import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PipelineStatusesTable } from '@/features/pipeline-statuses/pipeline-statuses-table'

/**
 * Project statuses page. Light composition only: gates access with
 * `pipeline-statuses.viewAny` and mounts the thin Project Statuses adapter,
 * which in turn mounts the generic table (`domain="pipeline-statuses"`). The
 * generic table owns config loading and loading/error/empty states; no
 * business logic or data fetching lives here.
 */
export default function PipelineStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="pipeline-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('pipelineStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <PipelineStatusesTable />
      </div>
    </Can>
  )
}
