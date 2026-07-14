import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Can } from '@/features/auth/can'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { projectCardsQueryKeyPrefix } from '@/features/projects/api'
import { ProjectCardGrid } from '@/features/projects/project-card-grid'
import { ProjectForm } from '@/features/projects/project-form'
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
 * views, but `ProjectsTable` — and the create Sheet nested inside it — is
 * only ever mounted in TABLE mode, so a create flow living there could never
 * be reached from GRID mode. `ProjectsTable`'s own create Sheet stays intact
 * and self-contained for any other, future standalone caller (`hideHeader`
 * defaults to `false`); this view simply doesn't exercise that path.
 */
export function ProjectsView() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { view, setView } = useProjectsViewPreference()
  const stats = useStatsPanel(PROJECTS_DOMAIN)
  const projectsTableRef = useRef<ProjectsTableHandle>(null)

  const [createOpen, setCreateOpen] = useState(false)

  const handleCreateSuccess = useCallback(
    () => {
      setCreateOpen(false)
      // Refresh whichever list is relevant: a no-op ref call if the table
      // isn't mounted (grid view), a stale-marking invalidation that only
      // refetches if the card grid is the one currently active.
      projectsTableRef.current?.refresh()
      void queryClient.invalidateQueries({ queryKey: projectCardsQueryKeyPrefix() })
    },
    [queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <ProjectsViewToggle view={view} onChange={setView} />
            <StatsToggleButton domain={PROJECTS_DOMAIN} isOpen={stats.isOpen} onToggle={stats.toggle} />
            <Can permission="projects.create">
              <Button onClick={() => setCreateOpen(true)}>
                <Plus aria-hidden="true" />
                {t('projects.form.newProject')}
              </Button>
            </Can>
          </div>
        }
      />

      <ModuleStatsPanel domain={PROJECTS_DOMAIN} isOpen={stats.isOpen} />

      {view === 'table' ? <ProjectsTable ref={projectsTableRef} hideHeader /> : <ProjectCardGrid />}

      <Sheet open={createOpen} onOpenChange={setCreateOpen}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${PROJECTS_DOMAIN}`}>
          <SheetHeader>
            <SheetTitle>{t('projects.form.createTitle')}</SheetTitle>
            <SheetDescription>{t('projects.form.createSubtitle')}</SheetDescription>
          </SheetHeader>
          <ProjectForm
            mode={{ type: 'create' }}
            onSuccess={handleCreateSuccess}
            onCancel={() => setCreateOpen(false)}
          />
        </SheetContent>
      </Sheet>
    </div>
  )
}
