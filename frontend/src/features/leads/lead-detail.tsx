import { useTranslation } from 'react-i18next'
import { Contact, Info, StickyNote } from 'lucide-react'
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
import type { LeadDetail as LeadDetailData } from '@/features/leads/types'

interface LeadDetailViewProps {
  lead: LeadDetailData
}

/**
 * Read-only detail of a single lead, fetched fresh from the (re-authorized)
 * detail endpoint. Composed from the shared detail kit; rendered by the
 * dedicated detail page (spec 0024, mirrors campaigns). A Lead has no
 * name/code of its own (D-3): the referent is the identity, the campaign is
 * the subtitle.
 */
export function LeadDetailView({ lead }: LeadDetailViewProps) {
  const { t } = useTranslation()
  const referentName = lead.referent?.name ?? t('leads.detail.unknownReferent')
  const createdAt = formatDateTime(lead.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={referentName} icon={<Contact />} />}
        title={referentName}
        subtitle={lead.campaign?.name}
      />

      <DetailSection title={t('leads.form.sections.contact.title')} icon={<Contact />}>
        <DetailGrid>
          <DetailField label={t('leads.form.referent')}>
            {lead.referent?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('leads.form.campaign')}>
            {lead.campaign ? `${lead.campaign.code} — ${lead.campaign.name}` : <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('leads.form.sections.details.title')} icon={<Info />}>
        <DetailGrid>
          <DetailField label={t('leads.form.operationalSite')}>
            {lead.operational_site?.label ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('leads.form.source')}>
            {lead.source?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('leads.form.operator')}>
            {lead.operator?.name ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('leads.form.notes')} icon={<StickyNote />}>
        <DetailGrid>
          <DetailField label={t('leads.form.notes')} full>
            {lead.notes ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {createdAt ? <DetailMeta label={t('leads.columns.createdAt')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
