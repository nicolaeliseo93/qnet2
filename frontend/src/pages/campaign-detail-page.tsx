import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { campaignDetailQueryKey, fetchCampaign } from '@/features/campaigns/api'
import { CampaignDetailView } from '@/features/campaigns/campaign-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated read-only page of a single campaign (spec 0023, mirrors
 * Projects). Fetches the fresh, re-authorized detail on mount — same query
 * key as before — and renders the unchanged presentational
 * `CampaignDetailView`. The "Edit" action is gated by the `permissions` block
 * of THIS response, not by a static ability: the backend remains the
 * authority.
 */
export default function CampaignDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const campaignId = parseEntityId(id)

  const {
    data: campaign,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(
    campaignDetailQueryKey(campaignId),
    () => fetchCampaign(campaignId as number),
    campaignId !== null,
  )

  useBreadcrumbTitle(`/campaigns/${id}`, campaign?.name)

  if (campaignId === null) {
    return <NotFoundPage />
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <Button variant="outline" asChild>
              <Link to="/campaigns">
                <ArrowLeft aria-hidden="true" />
                {t('common.back')}
              </Link>
            </Button>
            {campaign?.permissions.resource.update ? (
              <Button asChild>
                <Link to={`/campaigns/${campaignId}/edit`}>
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
            message={t('campaigns.detail.loadError')}
            retryLabel={t('common.retry')}
            onRetry={() => refetch()}
          />
        ) : isLoading || !campaign ? (
          <DetailLoading />
        ) : (
          <CampaignDetailView campaign={campaign} />
        )}
      </div>
    </div>
  )
}
