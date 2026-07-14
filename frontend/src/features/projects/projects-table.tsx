import { forwardRef, useCallback, useImperativeHandle, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { PageHeader } from '@/components/page-header'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Can } from '@/features/auth/can'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { projectColumnRenderers } from '@/features/projects/column-renderers'
import { deleteProject, fetchProject, projectDetailQueryKey } from '@/features/projects/api'
import { ProjectForm } from '@/features/projects/project-form'
import { ProjectDetailView } from '@/features/projects/project-detail'
import type { ProjectDetail } from '@/features/projects/types'

/** Domain key used to mount the generic table for projects. */
const PROJECTS_DOMAIN = 'projects'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

interface ProjectsTableProps {
  /**
   * Skips this component's own `PageHeader`/"New project" button (default
   * `false`, so any other caller keeps the current self-contained behaviour
   * unchanged). `ProjectsView` (spec 0026 layout fix) sets this to `true`: it
   * owns a single, unified `PageHeader` shared with the card-grid view, so
   * this adapter must not render a second one.
   */
  hideHeader?: boolean
}

/** Imperative handle so a caller that hides this component's own header (`hideHeader`) can still refresh its grid after a mutation it triggered itself. */
export interface ProjectsTableHandle {
  refresh: () => void
}

/**
 * Thin Projects adapter over the generic table. It mounts `<TableView>` with
 * the `projects` domain, its custom cell renderers and a row-action handler,
 * and owns the CRUD flows: opening a Sheet for view/edit/create, confirming +
 * running the delete mutation, and refreshing the SSRM grid after every
 * mutation via the table's imperative handle (spec 0025 Parte B — the
 * dedicated pages remain as deep-links, this adapter only changes the mount
 * point). Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export const ProjectsTable = forwardRef<ProjectsTableHandle, ProjectsTableProps>(
  function ProjectsTable({ hideHeader = false }, forwardedRef) {
    const { t } = useTranslation()
    const queryClient = useQueryClient()
    const invalidateStats = useInvalidateModuleStats(PROJECTS_DOMAIN)

    const tableRef = useRef<TableViewHandle>(null)
    const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

    useImperativeHandle(forwardedRef, () => ({ refresh: refreshGrid }), [refreshGrid])

    const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
    const [deletingId, setDeletingId] = useState<number | null>(null)

    const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

    const runDelete = useCallback(
      async (row: TableRow) => {
        setDeletingId(row.id)
        try {
          await deleteProject(row.id)
          toast.success(t('projects.form.deleted'))
          refreshGrid()
          invalidateStats()
        } catch (error) {
          const status = axios.isAxiosError(error) ? error.response?.status : undefined
          if (status === 403) {
            toast.error(t('projects.form.deleteForbidden'))
          } else if (status === 409) {
            toast.error(t('projects.form.deleteHasCampaigns'))
          } else {
            toast.error(t('projects.form.deleteError'))
          }
        } finally {
          setDeletingId(null)
        }
      },
      [refreshGrid, t, invalidateStats],
    )

    const handleAction: RowActionHandler = useCallback(
      (action: TableActionDefinition, row: TableRow) => {
        switch (action.key) {
          case 'view':
            setSheet({ kind: 'view', row })
            break
          case 'edit':
            setSheet({ kind: 'edit', row })
            break
          case 'delete':
            void runDelete(row)
            break
          default:
            break
        }
      },
      [runDelete],
    )

    const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

    const onSheetOpenChange = useCallback(
      (open: boolean) => {
        if (!open) {
          closeSheet()
        }
      },
      [closeSheet],
    )

    const onMutationSuccess = useCallback(
      (project: ProjectDetail) => {
        closeSheet()
        refreshGrid()
        invalidateStats()
        queryClient.invalidateQueries({ queryKey: projectDetailQueryKey(project.id) })
      },
      [closeSheet, refreshGrid, queryClient, invalidateStats],
    )

    return (
      <div className="flex flex-1 flex-col gap-4">
        {hideHeader ? null : (
          <PageHeader
            actions={
              <Can permission="projects.create">
                <Button onClick={() => setSheet({ kind: 'create' })}>
                  <Plus aria-hidden="true" />
                  {t('projects.form.newProject')}
                </Button>
              </Can>
            }
          />
        )}

        <TableView
          ref={tableRef}
          domain={PROJECTS_DOMAIN}
          renderers={projectColumnRenderers}
          onAction={handleAction}
          isBusy={isBusy}
        />

        <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
          <SheetContent className="gap-0" storageKey={`sheet-width:${PROJECTS_DOMAIN}`}>
            {sheet.kind === 'view' && (
              <>
                <SheetHeader className="sr-only">
                  <SheetTitle>{t('projects.detail.title')}</SheetTitle>
                  <SheetDescription>{t('projects.detail.subtitle')}</SheetDescription>
                </SheetHeader>
                <ViewProjectLoader projectId={sheet.row.id} />
              </>
            )}

            {sheet.kind === 'create' && (
              <>
                <SheetHeader>
                  <SheetTitle>{t('projects.form.createTitle')}</SheetTitle>
                  <SheetDescription>{t('projects.form.createSubtitle')}</SheetDescription>
                </SheetHeader>
                <ProjectForm
                  mode={{ type: 'create' }}
                  onSuccess={onMutationSuccess}
                  onCancel={closeSheet}
                />
              </>
            )}

            {sheet.kind === 'edit' && (
              <>
                <SheetHeader>
                  <SheetTitle>{t('projects.form.editTitle')}</SheetTitle>
                  <SheetDescription>{t('projects.form.editSubtitle')}</SheetDescription>
                </SheetHeader>
                <EditProjectLoader
                  projectId={sheet.row.id}
                  onSuccess={onMutationSuccess}
                  onCancel={closeSheet}
                />
              </>
            )}
          </SheetContent>
        </Sheet>
      </div>
    )
  },
)

interface ViewProjectLoaderProps {
  projectId: number
}

/**
 * Fetches the fresh project detail and hands it down to the (presentational)
 * `ProjectDetailView`, which owns no data-fetching state of its own.
 */
function ViewProjectLoader({ projectId }: ViewProjectLoaderProps) {
  const { t } = useTranslation()
  const {
    data: project,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(projectDetailQueryKey(projectId), () => fetchProject(projectId))

  if (isError) {
    return (
      <DetailError
        message={t('projects.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !project) {
    return <DetailLoading />
  }

  return <ProjectDetailView project={project} />
}

interface EditProjectLoaderProps {
  projectId: number
  onSuccess: (project: ProjectDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized project detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row snapshot.
 */
function EditProjectLoader({ projectId, onSuccess, onCancel }: EditProjectLoaderProps) {
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
