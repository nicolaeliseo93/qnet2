import { useTranslation } from 'react-i18next'
import { Contact, Database, History, Info, StickyNote } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
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
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { LEAD_STATUS_BADGE_CLASSES } from '@/features/leads/column-renderers'
import type { LeadDetailWithPermissions as LeadDetailData } from '@/features/leads/types'

interface LeadDetailViewProps {
  lead: LeadDetailData
}

/**
 * Read-only detail of a single lead, fetched fresh from the (re-authorized)
 * detail endpoint. Composed from the shared detail kit; rendered by the
 * dedicated detail page (spec 0024, mirrors campaigns). A Lead has no
 * name/code of its own (D-3, spec 0041 D-1: the identity is now the
 * anagrafica, not the referent); the registry is the identity, the campaign
 * is the subtitle.
 */
export function LeadDetailView({ lead }: LeadDetailViewProps) {
  const { t } = useTranslation()
  const registryName = lead.registry?.name ?? t('leads.detail.unknownRegistry')
  const createdAt = formatDateTime(lead.created_at)
  const extraFieldEntries = lead.extra_fields ? Object.entries(lead.extra_fields) : []

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={registryName} icon={<Contact />} />}
        title={registryName}
        subtitle={lead.campaign?.name}
      />

      <DetailSection title={t('leads.form.sections.contact.title')} icon={<Contact />}>
        <DetailGrid>
          <DetailField label={t('leads.form.registry')}>
            {lead.registry?.name ?? <DetailEmpty />}
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
          <DetailField label={t('leads.form.leadStatus')}>
            <Badge
              variant="secondary"
              className={cn(
                'h-5 min-h-5',
                lead.lead_status.color ? LEAD_STATUS_BADGE_CLASSES[lead.lead_status.color] : undefined,
              )}
            >
              {lead.lead_status.name}
            </Badge>
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

      {extraFieldEntries.length > 0 ? (
        <DetailSection title={t('leads.detail.importedData.title')} icon={<Database />}>
          <DetailGrid>
            {extraFieldEntries.map(([key, value]) => (
              <DetailField key={key} label={key}>
                {value}
              </DetailField>
            ))}
          </DetailGrid>
        </DetailSection>
      ) : null}

      {lead.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="leads" id={lead.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('leads.columns.createdAt')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
