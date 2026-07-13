import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchLead, leadDetailQueryKey } from '@/features/leads/api'
import { LeadDetailView } from '@/features/leads/lead-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated read-only page of a single lead (spec 0024, mirrors Campaigns).
 * Fetches the fresh, re-authorized detail on mount and renders the unchanged
 * presentational `LeadDetailView`. The "Edit" action is gated by the
 * `permissions` block of THIS response, not by a static ability: the backend
 * remains the authority. The referent's name (the lead's closest thing to an
 * identity, D-3) drives the breadcrumb.
 */
export default function LeadDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const leadId = parseEntityId(id)

  const {
    data: lead,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(leadDetailQueryKey(leadId), () => fetchLead(leadId as number), leadId !== null)

  useBreadcrumbTitle(`/leads/${id}`, lead?.referent?.name)

  if (leadId === null) {
    return <NotFoundPage />
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <Button variant="outline" asChild>
              <Link to="/leads">
                <ArrowLeft aria-hidden="true" />
                {t('common.back')}
              </Link>
            </Button>
            {lead?.permissions.resource.update ? (
              <Button asChild>
                <Link to={`/leads/${leadId}/edit`}>
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
            message={t('leads.detail.loadError')}
            retryLabel={t('common.retry')}
            onRetry={() => refetch()}
          />
        ) : isLoading || !lead ? (
          <DetailLoading />
        ) : (
          <LeadDetailView lead={lead} />
        )}
      </div>
    </div>
  )
}
