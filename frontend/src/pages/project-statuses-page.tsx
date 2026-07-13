import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ProjectStatusesTable } from '@/features/project-statuses/project-statuses-table'

/**
 * Project statuses page. Light composition only: gates access with
 * `project-statuses.viewAny` and mounts the thin Project Statuses adapter,
 * which in turn mounts the generic table (`domain="project-statuses"`). The
 * generic table owns config loading and loading/error/empty states; no
 * business logic or data fetching lives here.
 */
export default function ProjectStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="project-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('projectStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ProjectStatusesTable />
      </div>
    </Can>
  )
}
