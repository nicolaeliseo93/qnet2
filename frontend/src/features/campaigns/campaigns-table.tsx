import { useCallback, useRef, useState } from 'react'
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
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { campaignColumnRenderers } from '@/features/campaigns/column-renderers'
import { deleteCampaign, fetchCampaign, campaignDetailQueryKey } from '@/features/campaigns/api'
import { CampaignForm } from '@/features/campaigns/campaign-form'
import { CampaignDetailView } from '@/features/campaigns/campaign-detail'
import type { CampaignDetail } from '@/features/campaigns/types'

/** Domain key used to mount the generic table for campaigns. */
const CAMPAIGNS_DOMAIN = 'campaigns'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin Campaigns adapter over the generic table. It mounts `<TableView>` with
 * the `campaigns` domain, its custom cell renderers and a row-action handler,
 * and owns the CRUD flows: opening a Sheet for view/edit/create, confirming +
 * running the delete mutation, and refreshing the SSRM grid after every
 * mutation via the table's imperative handle (spec 0025 Parte B — the
 * dedicated pages remain as deep-links, this adapter only changes the mount
 * point). Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function CampaignsTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(CAMPAIGNS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(CAMPAIGNS_DOMAIN)
  const queryClient = useQueryClient()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteCampaign(row.id)
        toast.success(t('campaigns.form.deleted'))
        refreshGrid()
        invalidateStats()
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
        case 'activity':
          setActivityRow(row)
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
    (campaign: CampaignDetail) => {
      closeSheet()
      refreshGrid()
      invalidateStats()
      queryClient.invalidateQueries({ queryKey: campaignDetailQueryKey(campaign.id) })
    },
    [closeSheet, refreshGrid, queryClient, invalidateStats],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={CAMPAIGNS_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="campaigns.create">
              <Button onClick={() => setSheet({ kind: 'create' })}>
                <Plus aria-hidden="true" />
                {t('campaigns.form.newCampaign')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={CAMPAIGNS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={CAMPAIGNS_DOMAIN}
        renderers={campaignColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${CAMPAIGNS_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('campaigns.detail.title')}</SheetTitle>
                <SheetDescription>{t('campaigns.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewCampaignLoader campaignId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('campaigns.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('campaigns.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <CampaignForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('campaigns.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('campaigns.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditCampaignLoader
                campaignId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>

      <ResourceActivityDialog
        resource={CAMPAIGNS_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}

interface ViewCampaignLoaderProps {
  campaignId: number
}

/**
 * Fetches the fresh campaign detail and hands it down to the (presentational)
 * `CampaignDetailView`, which owns no data-fetching state of its own.
 */
function ViewCampaignLoader({ campaignId }: ViewCampaignLoaderProps) {
  const { t } = useTranslation()
  const {
    data: campaign,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(campaignDetailQueryKey(campaignId), () => fetchCampaign(campaignId))

  if (isError) {
    return (
      <DetailError
        message={t('campaigns.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !campaign) {
    return <DetailLoading />
  }

  return <CampaignDetailView campaign={campaign} />
}

interface EditCampaignLoaderProps {
  campaignId: number
  onSuccess: (campaign: CampaignDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized campaign detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row snapshot.
 */
function EditCampaignLoader({ campaignId, onSuccess, onCancel }: EditCampaignLoaderProps) {
  const { t } = useTranslation()
  const {
    data: campaign,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(campaignDetailQueryKey(campaignId), () => fetchCampaign(campaignId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('campaigns.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !campaign) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <CampaignForm mode={{ type: 'edit', campaign }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}
