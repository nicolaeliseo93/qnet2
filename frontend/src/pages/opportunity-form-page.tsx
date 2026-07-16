import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchOpportunity, opportunityDetailQueryKey } from '@/features/opportunities/api'
import { OpportunityForm, OpportunityFormSkeleton } from '@/features/opportunities/opportunity-form'
import { useOpportunityCreateMode } from '@/features/opportunities/use-opportunity-create-mode'
import type { OpportunityDetail } from '@/features/opportunities/types'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated create/edit page of an opportunity (spec 0040, mirrors Leads).
 * One page serves both routes: `/opportunities/new` (no `:id`) and
 * `/opportunities/:id/edit`. In edit mode the fresh, re-authorized detail is
 * fetched before the form mounts, so the partial PATCH starts from
 * authoritative values. Create also accepts `?lead_id=N` (spec 0040 MT-6):
 * the create-from-lead defaults, locked fields and the D-2 "already linked"
 * short-circuit are all resolved by `useOpportunityCreateMode`.
 */
export default function OpportunityFormPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const isEdit = id !== undefined
  const opportunityId = parseEntityId(id)
  const leadId = isEdit ? null : parseEntityId(searchParams.get('lead_id') ?? undefined)

  const {
    data: opportunity,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(
    opportunityDetailQueryKey(opportunityId),
    () => fetchOpportunity(opportunityId as number),
    opportunityId !== null,
  )
  const createMode = useOpportunityCreateMode(leadId)

  useBreadcrumbTitle(`/opportunities/${id}`, opportunity?.name)

  const onSuccess = useCallback(
    (saved: OpportunityDetail) => {
      queryClient.invalidateQueries({ queryKey: opportunityDetailQueryKey(saved.id) })
      void navigate(`/opportunities/${saved.id}`)
    },
    [navigate, queryClient],
  )

  const onCancel = useCallback(() => {
    void navigate(isEdit ? `/opportunities/${opportunityId}` : '/opportunities')
  }, [isEdit, navigate, opportunityId])

  if (isEdit && opportunityId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission={isEdit ? 'opportunities.update' : 'opportunities.create'}
      fallback={<p className="text-sm text-muted-foreground">{t('opportunities.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />

        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
          <header className="flex flex-col gap-1 border-b px-4 py-3">
            <h2 className="text-base font-semibold">
              {t(isEdit ? 'opportunities.form.editTitle' : 'opportunities.form.createTitle')}
            </h2>
            <p className="text-sm text-muted-foreground">
              {t(isEdit ? 'opportunities.form.editSubtitle' : 'opportunities.form.createSubtitle')}
            </p>
          </header>

          {isEdit ? (
            isError ? (
              <div className="flex flex-col items-start gap-3 p-4">
                <p className="text-sm text-destructive" role="alert">
                  {t('opportunities.detail.loadError')}
                </p>
                <Button variant="outline" size="sm" onClick={() => refetch()}>
                  {t('common.retry')}
                </Button>
              </div>
            ) : isLoading || !opportunity ? (
              <OpportunityFormSkeleton />
            ) : (
              <OpportunityForm mode={{ type: 'edit', opportunity }} onSuccess={onSuccess} onCancel={onCancel} />
            )
          ) : (
            <OpportunityCreateModeBody createMode={createMode} onSuccess={onSuccess} onCancel={onCancel} />
          )}
        </div>
      </div>
    </Can>
  )
}

interface OpportunityCreateModeBodyProps {
  createMode: ReturnType<typeof useOpportunityCreateMode>
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/**
 * Renders whatever `useOpportunityCreateMode` resolved: the loading skeleton
 * while a `lead_id`'s defaults are in flight, a retryable error, the D-2
 * "already linked" CTA, or the (possibly from-lead) create form.
 */
function OpportunityCreateModeBody({ createMode, onSuccess, onCancel }: OpportunityCreateModeBodyProps) {
  const { t } = useTranslation()

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
