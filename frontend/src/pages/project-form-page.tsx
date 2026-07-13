import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProject, projectDetailQueryKey } from '@/features/projects/api'
import { ProjectForm } from '@/features/projects/project-form'
import type { ProjectDetail } from '@/features/projects/types'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated create/edit page of a project (spec 0023, mirrors 0022). One page
 * serves both routes: `/projects/new` (no `:id`) and `/projects/:id/edit`. In
 * edit mode the fresh, re-authorized detail is fetched before the form
 * mounts, so the partial PATCH starts from authoritative values. `ProjectForm`
 * and its hook/payload are reused as-is.
 */
export default function ProjectFormPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const isEdit = id !== undefined
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

  const onSuccess = useCallback(
    (saved: ProjectDetail) => {
      queryClient.invalidateQueries({ queryKey: projectDetailQueryKey(saved.id) })
      void navigate(`/projects/${saved.id}`)
    },
    [navigate, queryClient],
  )

  const onCancel = useCallback(() => {
    void navigate(isEdit ? `/projects/${projectId}` : '/projects')
  }, [isEdit, navigate, projectId])

  if (isEdit && projectId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission={isEdit ? 'projects.update' : 'projects.create'}
      fallback={<p className="text-sm text-muted-foreground">{t('projects.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />

        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
          <header className="flex flex-col gap-1 border-b px-4 py-3">
            <h2 className="text-base font-semibold">
              {t(isEdit ? 'projects.form.editTitle' : 'projects.form.createTitle')}
            </h2>
            <p className="text-sm text-muted-foreground">
              {t(isEdit ? 'projects.form.editSubtitle' : 'projects.form.createSubtitle')}
            </p>
          </header>

          {isError ? (
            <div className="flex flex-col items-start gap-3 p-4">
              <p className="text-sm text-destructive" role="alert">
                {t('projects.detail.loadError')}
              </p>
              <Button variant="outline" size="sm" onClick={() => refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : isEdit && (isLoading || !project) ? (
            <div className="flex flex-col gap-4 p-4" aria-hidden="true">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
            </div>
          ) : (
            <ProjectForm
              mode={project ? { type: 'edit', project } : { type: 'create' }}
              onSuccess={onSuccess}
              onCancel={onCancel}
            />
          )}
        </div>
      </div>
    </Can>
  )
}
