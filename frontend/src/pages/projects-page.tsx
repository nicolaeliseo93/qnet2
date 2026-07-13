import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ProjectsTable } from '@/features/projects/projects-table'

/**
 * Projects page. Light composition only: gates access with
 * `projects.viewAny` and mounts the thin Projects adapter, which in turn
 * mounts the generic table (`domain="projects"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here.
 */
export default function ProjectsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="projects.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('projects.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ProjectsTable />
      </div>
    </Can>
  )
}
