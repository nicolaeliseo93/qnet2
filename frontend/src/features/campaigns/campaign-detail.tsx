import { useTranslation } from 'react-i18next'
import { CalendarRange, FolderKanban, Megaphone, Tags, Wallet } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDecimal } from '@/features/products/column-renderers'
import type { CampaignDetail as CampaignDetailData } from '@/features/campaigns/types'

interface CampaignDetailViewProps {
  campaign: CampaignDetailData
}

/** Formats a `Y-m-d` date-only value, blank when missing/invalid. */
function formatDate(value: string | null, language: string): string {
  if (!value) {
    return ''
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  return new Intl.DateTimeFormat(language, { dateStyle: 'medium' }).format(date)
}

/**
 * Read-only detail of a single campaign, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered by the dedicated detail page (spec 0023, mirrors 0022/Projects).
 * The 4 classification fields always show the EFFECTIVE value (BR-2: read
 * through the project when linked) — `CampaignResource` already resolves
 * that server-side, so this view never special-cases `derived_from_project`.
 */
export function CampaignDetailView({ campaign }: CampaignDetailViewProps) {
  const { t, i18n } = useTranslation()
  const createdAt = formatDateTime(campaign.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={campaign.name} icon={<Megaphone />} />}
        title={campaign.name}
        subtitle={campaign.code}
        badges={
          campaign.derived_from_project ? (
            <Badge variant="secondary">{t('campaigns.detail.linkedToProject')}</Badge>
          ) : (
            <Badge variant="outline">{t('campaigns.detail.standalone')}</Badge>
          )
        }
      />

      {campaign.description && (
        <DetailSection title={t('campaigns.form.description')}>
          <DetailGrid>
            <DetailField label={t('campaigns.form.description')} full>
              {campaign.description}
            </DetailField>
          </DetailGrid>
        </DetailSection>
      )}

      <DetailSection title={t('campaigns.form.sections.project.title')} icon={<FolderKanban />}>
        <DetailGrid>
          <DetailField label={t('campaigns.form.project')}>
            {campaign.project ? `${campaign.project.code} — ${campaign.project.name}` : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.registry')}>
            {campaign.registry?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.source')}>
            {campaign.source?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.partner')}>
            {campaign.partner?.name ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('campaigns.form.sections.classification.title')} icon={<Tags />}>
        <DetailGrid>
          <DetailField label={t('campaigns.form.status')}>
            {campaign.project_status?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.businessFunction')}>
            {campaign.business_function?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.state')}>
            {campaign.state?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.productCategory')}>
            {campaign.product_category?.name ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('campaigns.form.sections.planning.title')} icon={<CalendarRange />}>
        <DetailGrid>
          <DetailField label={t('campaigns.form.startDate')}>
            {formatDate(campaign.start_date, i18n.language) || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.endDate')}>
            {formatDate(campaign.end_date, i18n.language) || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('campaigns.form.targetLead')}>
            {campaign.target_lead ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('campaigns.detail.budget')} icon={<Wallet />}>
        <DetailGrid>
          <DetailField label={t('campaigns.form.totalBudget')}>
            {campaign.total_budget !== null ? formatDecimal(campaign.total_budget) : <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {createdAt ? (
        <DetailMeta label={t('campaigns.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
