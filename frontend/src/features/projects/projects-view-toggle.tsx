import { useTranslation } from 'react-i18next'
import { LayoutGrid, Table } from 'lucide-react'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { ProjectsView } from '@/features/projects/use-projects-view-preference'

interface ProjectsViewToggleProps {
  view: ProjectsView
  onChange: (view: ProjectsView) => void
}

/** Grid/table switch for the projects page (spec 0026 AC-008), compact sizing. */
export function ProjectsViewToggle({ view, onChange }: ProjectsViewToggleProps) {
  const { t } = useTranslation()

  return (
    <Tabs
      value={view}
      onValueChange={(value) => onChange(value as ProjectsView)}
      aria-label={t('projects.view.toggleLabel')}
    >
      <TabsList className="h-9 w-fit">
        <TabsTrigger value="grid" className="gap-1 px-2.5 py-1 text-xs">
          <LayoutGrid className="size-3.5" aria-hidden="true" />
          {t('projects.view.grid')}
        </TabsTrigger>
        <TabsTrigger value="table" className="gap-1 px-2.5 py-1 text-xs">
          <Table className="size-3.5" aria-hidden="true" />
          {t('projects.view.table')}
        </TabsTrigger>
      </TabsList>
    </Tabs>
  )
}
