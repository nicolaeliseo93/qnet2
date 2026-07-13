import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProject, projectDetailQueryKey } from '@/features/projects/api'
import { ProjectDetailView } from '@/features/projects/project-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated read-only page of a single project (spec 0023, mirrors 0022).
 * Fetches the fresh, re-authorized detail on mount — same query key as
 * before — and renders the unchanged presentational `ProjectDetailView`. The
 * "Edit" action is gated by the `permissions` block of THIS response, not by a
 * static ability: the backend remains the authority.
 */
export default function ProjectDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const projectId = parseEntityId(id)

  const {
    data: project,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(
    projectDetailQueryKey(projectId),
    () => fetchProject(projectId as number),
    projectId !== null,
  )

  useBreadcrumbTitle(`/projects/${id}`, project?.name)

  if (projectId === null) {
    return <NotFoundPage />
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <Button variant="outline" asChild>
              <Link to="/projects">
                <ArrowLeft aria-hidden="true" />
                {t('common.back')}
              </Link>
            </Button>
            {project?.permissions.resource.update ? (
              <Button asChild>
                <Link to={`/projects/${projectId}/edit`}>
                  <Pencil aria-hidden="true" />
                  {t('common.edit')}
                </Link>
              </Button>
            ) : null}
          </>
        }
      />

      <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
        {isError ? (
          <DetailError
            message={t('projects.detail.loadError')}
            retryLabel={t('common.retry')}
            onRetry={() => refetch()}
          />
        ) : isLoading || !project ? (
          <DetailLoading />
        ) : (
          <ProjectDetailView project={project} />
        )}
      </div>
    </div>
  )
}
