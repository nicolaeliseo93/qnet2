import { useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { projectCardsQueryKeyPrefix } from '@/features/projects/api'
import { ProjectCardGrid } from '@/features/projects/project-card-grid'
import { ProjectsTable, type ProjectsTableHandle } from '@/features/projects/projects-table'
import { ProjectsViewToggle } from '@/features/projects/projects-view-toggle'
import { useProjectsViewPreference } from '@/features/projects/use-projects-view-preference'

/** Domain key used for the projects module statistics (spec 0026). */
const PROJECTS_DOMAIN = 'projects'

/**
 * Composition root of the projects page. A SINGLE `PageHeader` (breadcrumb +
 * actions only, no title/subtitle — matching every other module) is shared by
 * both views: `[view toggle, stats toggle, "New project"]`, same row, same
 * height, always visible regardless of the grid/table choice. Only the body
 * below it swaps with the toggle. The statistics panel does NOT depend on the
 * view either: its open state is a single `useStatsPanel('projects')` here,
 * rendered once, outside the grid/table branch.
 *
 * Creation is owned here too (not by `ProjectsTable`, which the view mounts
 * with `hideHeader`): the "New project" button must work identically in both
 * views, and `ProjectsTable`'s own create flow is only ever mounted in TABLE
 * mode. It delegates the open mode (modal Sheet vs dedicated page) to
 * `useModuleOpener('projects')`, resolved from the user's preference (spec
 * 0042); `ProjectsTable` keeps its own, self-contained opener for view/edit
 * and for any future standalone caller (`hideHeader` defaults to `false`).
 */
export function ProjectsView() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { view, setView } = useProjectsViewPreference()
  const stats = useStatsPanel(PROJECTS_DOMAIN)
  const projectsTableRef = useRef<ProjectsTableHandle>(null)

  const handleCreateSuccess = useCallback(() => {
    // Refresh whichever list is relevant: a no-op ref call if the table
    // isn't mounted (grid view), a stale-marking invalidation that only
    // refetches if the card grid is the one currently active.
    projectsTableRef.current?.refresh()
    void queryClient.invalidateQueries({ queryKey: projectCardsQueryKeyPrefix() })
  }, [queryClient])

  const { openCreate, sheet } = useModuleOpener(PROJECTS_DOMAIN, { onSaved: handleCreateSuccess })

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <ProjectsViewToggle view={view} onChange={setView} />
            <StatsToggleButton domain={PROJECTS_DOMAIN} isOpen={stats.isOpen} onToggle={stats.toggle} />
            <Can permission="projects.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('projects.form.newProject')}
              </Button>
            </Can>
          </div>
        }
      />

      <ModuleStatsPanel domain={PROJECTS_DOMAIN} isOpen={stats.isOpen} />

      {view === 'table' ? <ProjectsTable ref={projectsTableRef} hideHeader /> : <ProjectCardGrid />}

      {sheet}
    </div>
  )
}
