/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchOpportunityWorkflow } from '@/features/opportunity-workflows/api'
import { OpportunityWorkflowForm } from '@/features/opportunity-workflows/opportunity-workflow-form'
import { OpportunityWorkflowDetailView } from '@/features/opportunity-workflows/opportunity-workflow-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { OpportunityWorkflowDetail } from '@/features/opportunity-workflows/types'

/** Query key for a single opportunity workflow's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['opportunity-workflows', 'detail', id] as const
}

/**
 * Content-only `opportunity-workflows` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function OpportunityWorkflowDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: opportunityWorkflow,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchOpportunityWorkflow(id))

  if (isError) {
    return (
      <DetailError
        message={t('opportunityWorkflows.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !opportunityWorkflow) {
    return <DetailLoading />
  }

  return <OpportunityWorkflowDetailView opportunityWorkflow={opportunityWorkflow} />
}

export function OpportunityWorkflowFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: OpportunityWorkflowDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <OpportunityWorkflowForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
    )
  }

  return (
    <OpportunityWorkflowEditScreen
      opportunityWorkflowId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface OpportunityWorkflowEditScreenProps {
  opportunityWorkflowId: number
  onSuccess: (opportunityWorkflow: OpportunityWorkflowDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized opportunity workflow detail before
 * mounting the edit form, so the PATCH starts from authoritative values
 * rather than a stale snapshot.
 */
function OpportunityWorkflowEditScreen({
  opportunityWorkflowId,
  onSuccess,
  onCancel,
}: OpportunityWorkflowEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: opportunityWorkflow,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(opportunityWorkflowId), () => fetchOpportunityWorkflow(opportunityWorkflowId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('opportunityWorkflows.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !opportunityWorkflow) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <OpportunityWorkflowForm
      mode={{ type: 'edit', opportunityWorkflow }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'opportunity-workflows',
  basePath: '/opportunity-workflows',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.opportunityWorkflows',
  DetailScreen: OpportunityWorkflowDetailScreen,
  FormScreen: OpportunityWorkflowFormScreen,
}
