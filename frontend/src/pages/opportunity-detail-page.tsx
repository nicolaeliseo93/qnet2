import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchOpportunity, opportunityDetailQueryKey } from '@/features/opportunities/api'
import { OpportunityDetailView } from '@/features/opportunities/opportunity-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated read-only page of a single opportunity (spec 0040, mirrors
 * Leads). Fetches the fresh, re-authorized detail on mount and renders the
 * unchanged presentational `OpportunityDetailView`. The "Edit" action is
 * gated by the `permissions` block of THIS response, not by a static
 * ability: the backend remains the authority.
 */
export default function OpportunityDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const opportunityId = parseEntityId(id)

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

  useBreadcrumbTitle(`/opportunities/${id}`, opportunity?.name)

  if (opportunityId === null) {
    return <NotFoundPage />
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <Button variant="outline" asChild>
              <Link to="/opportunities">
                <ArrowLeft aria-hidden="true" />
                {t('common.back')}
              </Link>
            </Button>
            {opportunity?.permissions.resource.update ? (
              <Button asChild>
                <Link to={`/opportunities/${opportunityId}/edit`}>
                  <Pencil aria-hidden="true" />
                  {t('common.edit')}
                </Link>
              </Button>
            ) : null}
          </>
        }
      />

      <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
        {isError ? (
          <DetailError
            message={t('opportunities.detail.loadError')}
            retryLabel={t('common.retry')}
            onRetry={() => refetch()}
          />
        ) : isLoading || !opportunity ? (
          <DetailLoading />
        ) : (
          <OpportunityDetailView opportunity={opportunity} />
        )}
      </div>
    </div>
  )
}
