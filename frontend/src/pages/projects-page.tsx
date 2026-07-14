import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ProjectsView } from '@/features/projects/projects-view'

/**
 * Projects page. Light composition only: gates access with
 * `projects.viewAny` and mounts the card-grid/table composition (spec 0026).
 * No business logic or data fetching lives here.
 */
export default function ProjectsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="projects.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('projects.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ProjectsView />
      </div>
    </Can>
  )
}
