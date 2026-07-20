/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Handshake } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Can } from '@/features/auth/can'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchLead, leadDetailQueryKey } from '@/features/leads/api'
import { LeadForm } from '@/features/leads/lead-form'
import { LeadDetailView } from '@/features/leads/lead-detail'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { OPPORTUNITIES_DOMAIN } from '@/features/opportunities/api'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { LeadDetail } from '@/features/leads/types'

/**
 * Content-only `leads` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`). Moved verbatim from
 * `LeadsTable`'s inline loaders, which the rewire removed.
 */
export function LeadDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: lead,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(leadDetailQueryKey(id), () => fetchLead(id))

  if (isError) {
    return (
      <DetailError
        message={t('leads.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !lead) {
    return <DetailLoading />
  }

  return <LeadDetailView lead={lead} />
}

export function LeadFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: LeadDetail) => {
    queryClient.invalidateQueries({ queryKey: leadDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <LeadForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <LeadEditScreen leadId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface LeadEditScreenProps {
  leadId: number
  onSuccess: (lead: LeadDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized lead detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function LeadEditScreen({ leadId, onSuccess, onCancel }: LeadEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: lead,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(leadDetailQueryKey(leadId), () => fetchLead(leadId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('leads.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !lead) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <LeadForm mode={{ type: 'edit', lead }} onSuccess={onSuccess} onCancel={onCancel} />
}

/**
 * Extra action for the generic dedicated detail page only (never shown in
 * the quick-view Sheet — parity with the non-goal decision of spec 0040's
 * MT-6: "row action nella tabella leads"). Reuses the SAME query key as
 * `LeadDetailScreen`, so React Query dedupes the fetch instead of firing a
 * second request.
 */
export function LeadDetailPageActions({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { data: lead } = useEntityDetail(leadDetailQueryKey(id), () => fetchLead(id))

  // Opens the OPPORTUNITIES form seeded with this lead (spec 0045), following
  // the user's opportunities open mode instead of always navigating to a
  // page. On save, invalidates THIS lead's detail query (AC-025) so the
  // button flips to "Go to opportunity" once `lead.opportunity` comes back
  // populated.
  const { openCreateWith: openOpportunityWith, sheet: opportunitySheet } = useModuleOpener(
    OPPORTUNITIES_DOMAIN,
    { onSaved: () => queryClient.invalidateQueries({ queryKey: leadDetailQueryKey(id) }) },
  )

  if (!lead) {
    return null
  }

  if (lead.opportunity) {
    return (
      <Button variant="outline" asChild>
        <Link to={`/opportunities/${lead.opportunity.id}`}>
          <Handshake aria-hidden="true" />
          {t('leads.detail.goToOpportunity')}
        </Link>
      </Button>
    )
  }

  return (
    <Can permission="opportunities.create">
      <Button variant="outline" onClick={() => openOpportunityWith({ lead_id: id })}>
        <Handshake aria-hidden="true" />
        {t('leads.detail.createOpportunity')}
      </Button>
      {opportunitySheet}
    </Can>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'leads',
  basePath: '/leads',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.leads',
  DetailScreen: LeadDetailScreen,
  FormScreen: LeadFormScreen,
  DetailPageActions: LeadDetailPageActions,
}