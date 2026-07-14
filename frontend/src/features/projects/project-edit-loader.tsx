import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProject, projectDetailQueryKey } from '@/features/projects/api'
import { ProjectForm } from '@/features/projects/project-form'
import type { ProjectDetail } from '@/features/projects/types'

interface ProjectEditLoaderProps {
  projectId: number
  onSuccess: (project: ProjectDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized project detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row / card snapshot. Shared by the table Sheet and the card-grid Sheet.
 */
export function ProjectEditLoader({ projectId, onSuccess, onCancel }: ProjectEditLoaderProps) {
  const { t } = useTranslation()
  const {
    data: project,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(projectDetailQueryKey(projectId), () => fetchProject(projectId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('projects.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !project) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <ProjectForm mode={{ type: 'edit', project }} onSuccess={onSuccess} onCancel={onCancel} />
}
