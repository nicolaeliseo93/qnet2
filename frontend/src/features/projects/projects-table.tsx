import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { projectColumnRenderers } from '@/features/projects/column-renderers'
import { deleteProject } from '@/features/projects/api'

/** Domain key used to mount the generic table for projects. */
const PROJECTS_DOMAIN = 'projects'

/**
 * Thin Projects adapter over the generic table. It mounts `<TableView>` with
 * the `projects` domain, its custom cell renderers and a row-action handler.
 * View/create/edit are dedicated pages (spec 0023, mirrors 0022): the row
 * actions and the "New" button navigate there. Only the delete stays here — it
 * runs inline and refreshes the SSRM grid through the table's imperative
 * handle, surfacing the backend's 409 when the project still has campaigns
 * (BR-5). Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function ProjectsTable() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteProject(row.id)
        toast.success(t('projects.form.deleted'))
        refreshGrid()
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
    [refreshGrid, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          void navigate(`/projects/${row.id}`)
          break
        case 'edit':
          void navigate(`/projects/${row.id}/edit`)
          break
        case 'delete':
          void runDelete(row)
          break
        default:
          break
      }
    },
    [navigate, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="projects.create">
            <Button onClick={() => void navigate('/projects/new')}>
              <Plus aria-hidden="true" />
              {t('projects.form.newProject')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={PROJECTS_DOMAIN}
        renderers={projectColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />
    </div>
  )
}
