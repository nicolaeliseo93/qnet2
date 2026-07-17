/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchLeadStatus } from '@/features/lead-statuses/api'
import { LeadStatusForm } from '@/features/lead-statuses/lead-status-form'
import { LeadStatusDetailView } from '@/features/lead-statuses/lead-status-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { LeadStatusDetail } from '@/features/lead-statuses/types'

/** Query key for a single lead status's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['lead-statuses', 'detail', id] as const
}

/**
 * Content-only `lead-statuses` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `LeadStatusesTable`'s inline loaders, which the rewire removed.
 */
export function LeadStatusDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: leadStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchLeadStatus(id))

  if (isError) {
    return (
      <DetailError
        message={t('leadStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !leadStatus) {
    return <DetailLoading />
  }

  return <LeadStatusDetailView leadStatus={leadStatus} />
}

export function LeadStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: LeadStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <LeadStatusForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return (
    <LeadStatusEditScreen leadStatusId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface LeadStatusEditScreenProps {
  leadStatusId: number
  onSuccess: (leadStatus: LeadStatusDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized lead status detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function LeadStatusEditScreen({ leadStatusId, onSuccess, onCancel }: LeadStatusEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: leadStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(leadStatusId), () => fetchLeadStatus(leadStatusId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('leadStatuses.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !leadStatus) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <LeadStatusForm mode={{ type: 'edit', leadStatus }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'lead-statuses',
  basePath: '/lead-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.leadStatuses',
  DetailScreen: LeadStatusDetailScreen,
  FormScreen: LeadStatusFormScreen,
}