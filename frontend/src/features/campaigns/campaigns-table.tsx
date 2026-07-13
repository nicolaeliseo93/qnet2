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
import { campaignColumnRenderers } from '@/features/campaigns/column-renderers'
import { deleteCampaign } from '@/features/campaigns/api'

/** Domain key used to mount the generic table for campaigns. */
const CAMPAIGNS_DOMAIN = 'campaigns'

/**
 * Thin Campaigns adapter over the generic table. It mounts `<TableView>` with
 * the `campaigns` domain, its custom cell renderers and a row-action handler.
 * View/create/edit are dedicated pages (spec 0023, mirrors Projects): the row
 * actions and the "New" button navigate there. Only the delete stays here — it
 * runs inline and refreshes the SSRM grid through the table's imperative
 * handle. Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function CampaignsTable() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteCampaign(row.id)
        toast.success(t('campaigns.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('campaigns.form.deleteForbidden'))
        } else {
          toast.error(t('campaigns.form.deleteError'))
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
          void navigate(`/campaigns/${row.id}`)
          break
        case 'edit':
          void navigate(`/campaigns/${row.id}/edit`)
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
          <Can permission="campaigns.create">
            <Button onClick={() => void navigate('/campaigns/new')}>
              <Plus aria-hidden="true" />
              {t('campaigns.form.newCampaign')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={CAMPAIGNS_DOMAIN}
        renderers={campaignColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />
    </div>
  )
}
