import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import {
  fetchOpportunity,
  opportunityDetailQueryKey,
} from '@/features/opportunities/api'
import { OpportunityForm, OpportunityFormSkeleton } from '@/features/opportunities/opportunity-form'
import { OpportunityDetailView } from '@/features/opportunities/opportunity-detail'
import { useOpportunityCreateMode } from '@/features/opportunities/use-opportunity-create-mode'
import { parseEntityId } from '@/routes/entity-id'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
} from '@/features/modules/types'
import type { OpportunityDetail } from '@/features/opportunities/types'

/**
 * Content-only `opportunities` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `OpportunitiesTable`'s inline loaders, which the rewire removed.
 */
export function OpportunityDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: opportunity,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(opportunityDetailQueryKey(id), () => fetchOpportunity(id))

  if (isError) {
    return (
      <DetailError
        message={t('opportunities.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !opportunity) {
    return <DetailLoading />
  }

  return <OpportunityDetailView opportunity={opportunity} />
}

/**
 * Create branch reads `?lead_id=N` from the CURRENT route (spec 0040 MT-6):
 * harmless when mounted from the table's "New" action (that route never
 * carries the param) and required when mounted at the `/opportunities/new`
 * deep-link coming from a lead's "Create opportunity" action.
 */
export function OpportunityFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()
  const [searchParams] = useSearchParams()

  const handleSuccess = (saved: OpportunityDetail) => {
    queryClient.invalidateQueries({ queryKey: opportunityDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'edit') {
    return (
      <OpportunityEditScreen opportunityId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
    )
  }

  const leadId = parseEntityId(searchParams.get('lead_id') ?? undefined)
  return <OpportunityCreateScreen leadId={leadId} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface OpportunityCreateScreenProps {
  leadId: number | null
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/** Resolves the `?lead_id=N` create-from-lead context (D-2 short-circuit included) before mounting the form. */
function OpportunityCreateScreen({ leadId, onSuccess, onCancel }: OpportunityCreateScreenProps) {
  const { t } = useTranslation()
  const createMode = useOpportunityCreateMode(leadId)

  if (createMode.status === 'loading') {
    return <OpportunityFormSkeleton />
  }

  if (createMode.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('opportunities.form.defaultsLoadError')}
        </p>
        <Button variant="outline" size="sm" onClick={createMode.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (createMode.status === 'existing') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm font-medium">{t('opportunities.form.existingOpportunityTitle')}</p>
        <p className="text-sm text-muted-foreground">
          {t('opportunities.form.existingOpportunityDescription')}
        </p>
        <Button asChild>
          <Link to={`/opportunities/${createMode.existingOpportunityId}`}>
            {t('opportunities.form.goToExistingOpportunity')}
          </Link>
        </Button>
      </div>
    )
  }

  return <OpportunityForm mode={createMode.mode} onSuccess={onSuccess} onCancel={onCancel} />
}

interface OpportunityEditScreenProps {
  opportunityId: number
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized opportunity detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function OpportunityEditScreen({ opportunityId, onSuccess, onCancel }: OpportunityEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: opportunity,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(opportunityDetailQueryKey(opportunityId), () => fetchOpportunity(opportunityId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('opportunities.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !opportunity) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <OpportunityForm mode={{ type: 'edit', opportunity }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}
