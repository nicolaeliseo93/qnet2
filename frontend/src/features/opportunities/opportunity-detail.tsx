import { useTranslation } from 'react-i18next'
import { Building2, Contact, Handshake, History, Users } from 'lucide-react'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailPerson,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDecimal } from '@/features/products/column-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { OpportunityDetailWithPermissions as OpportunityDetailData } from '@/features/opportunities/types'

interface OpportunityDetailViewProps {
  opportunity: OpportunityDetailData
}

/** Formats a `Y-m-d` date column, blank when missing/invalid — mirrors the column renderer. */
function formatDate(value: string | null): string | null {
  if (!value) {
    return null
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString()
}

/**
 * Read-only detail of a single opportunity, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered by the dedicated detail page (spec 0040, mirrors leads).
 */
export function OpportunityDetailView({ opportunity }: OpportunityDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(opportunity.created_at)
  const startDate = formatDate(opportunity.start_date)
  const expectedCloseDate = formatDate(opportunity.expected_close_date)
  const estimatedValue = formatDecimal(opportunity.estimated_value)
  const sortedManagers = [...opportunity.managers].sort((a, b) => a.position - b.position)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={opportunity.name} icon={<Handshake />} />}
        title={opportunity.name}
        subtitle={opportunity.registry?.name}
      />

      <DetailSection title={t('opportunities.form.sections.identity.title')} icon={<Contact />}>
        <DetailGrid>
          <DetailField label={t('opportunities.form.registry')}>
            {opportunity.registry?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.referent')}>
            {opportunity.referent?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.commercial')}>
            {opportunity.commercial?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.reporter')}>
            {opportunity.reporter?.name ?? <DetailEmpty />}
          </DetailField>
          {opportunity.lead ? (
            <DetailField label={t('opportunities.detail.sourceLead')}>{opportunity.lead.label}</DetailField>
          ) : null}
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('opportunities.form.sections.classification.title')} icon={<Building2 />}>
        <DetailGrid>
          <DetailField label={t('opportunities.form.company')}>
            {opportunity.company?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.companySite')}>
            {opportunity.company_site?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.businessFunction')}>
            {opportunity.business_function?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.operationalSite')}>
            {opportunity.operational_site?.label ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.productCategory')}>
            {opportunity.product_category?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.source')}>
            {opportunity.source?.name ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('opportunities.form.sections.team.title')} icon={<Users />}>
        <DetailGrid>
          <DetailField label={t('opportunities.form.supervisor')}>
            {opportunity.supervisor?.name ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
        <div className="mt-4 flex flex-col gap-2">
          <span className="text-xs font-medium text-muted-foreground">{t('opportunities.form.managers')}</span>
          {sortedManagers.length > 0 ? (
            <ul className="flex flex-col gap-2">
              {sortedManagers.map((manager) => (
                <li key={manager.id} className="flex items-center gap-2">
                  <span className="w-5 shrink-0 text-xs font-semibold text-muted-foreground">
                    {manager.position}
                  </span>
                  <DetailPerson name={manager.name} />
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </div>
      </DetailSection>

      <DetailSection title={t('opportunities.form.sections.planning.title')}>
        <DetailGrid>
          <DetailField label={t('opportunities.form.startDate')}>{startDate ?? <DetailEmpty />}</DetailField>
          <DetailField label={t('opportunities.form.expectedCloseDate')}>
            {expectedCloseDate ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.estimatedValue')}>
            {estimatedValue || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.successProbability')}>
            {opportunity.success_probability !== null ? `${opportunity.success_probability}%` : <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {opportunity.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="opportunities" id={opportunity.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('opportunities.columns.createdAt')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
