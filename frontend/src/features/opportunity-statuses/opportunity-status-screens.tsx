/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchOpportunityStatus } from '@/features/opportunity-statuses/api'
import { OpportunityStatusForm } from '@/features/opportunity-statuses/opportunity-status-form'
import { OpportunityStatusDetailView } from '@/features/opportunity-statuses/opportunity-status-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { OpportunityStatusDetail } from '@/features/opportunity-statuses/types'

/** Query key for a single opportunity status's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['opportunity-statuses', 'detail', id] as const
}

/**
 * Content-only `opportunity-statuses` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`). Mirrors
 * `LeadStatusDetailScreen`/`LeadStatusFormScreen` (spec 0043).
 */
export function OpportunityStatusDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: opportunityStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchOpportunityStatus(id))

  if (isError) {
    return (
      <DetailError
        message={t('opportunityStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !opportunityStatus) {
    return <DetailLoading />
  }

  return <OpportunityStatusDetailView opportunityStatus={opportunityStatus} />
}

export function OpportunityStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: OpportunityStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <OpportunityStatusForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
    )
  }

  return (
    <OpportunityStatusEditScreen
      opportunityStatusId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface OpportunityStatusEditScreenProps {
  opportunityStatusId: number
  onSuccess: (opportunityStatus: OpportunityStatusDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized opportunity status detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values
 * rather than a stale snapshot.
 */
function OpportunityStatusEditScreen({
  opportunityStatusId,
  onSuccess,
  onCancel,
}: OpportunityStatusEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: opportunityStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(opportunityStatusId), () => fetchOpportunityStatus(opportunityStatusId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('opportunityStatuses.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !opportunityStatus) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <OpportunityStatusForm
      mode={{ type: 'edit', opportunityStatus }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'opportunity-statuses',
  basePath: '/opportunity-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.opportunityStatuses',
  DetailScreen: OpportunityStatusDetailScreen,
  FormScreen: OpportunityStatusFormScreen,
}
