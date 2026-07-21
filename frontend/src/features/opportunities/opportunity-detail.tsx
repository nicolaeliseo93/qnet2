import { useTranslation } from 'react-i18next'
import { Building2, Contact, Handshake, History, Paperclip, Users } from 'lucide-react'
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
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDecimal } from '@/features/products/column-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { OPPORTUNITY_STATUS_BADGE_CLASSES } from '@/features/opportunities/column-renderers'
import type {
  OpportunityDetailWithPermissions as OpportunityDetailData,
  OpportunityProductLine,
} from '@/features/opportunities/types'

interface OpportunityDetailViewProps {
  opportunity: OpportunityDetailData
}

/** Read-only list of the opportunity's business-function + product-category rows (spec 0040 amendment rev.3, AC-101). */
function ProductLinesList({ lines }: { lines: OpportunityProductLine[] }) {
  if (lines.length === 0) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex flex-col gap-1">
      {lines.map((line) => (
        <li key={line.id}>
          <span className="font-medium">{line.business_function.name}</span>
          <span className="text-muted-foreground"> — {line.product_category.name}</span>
        </li>
      ))}
    </ul>
  )
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
 * Derives the attachments abilities and renders the documents section. Kept
 * as its own mount (rather than calling `useAbilities()` unconditionally in
 * `OpportunityDetailView`) so the underlying `useQuery` calls only run while
 * the section is actually authorized to render.
 */
function OpportunityDocumentsPanel({ opportunityId }: { opportunityId: number }) {
  const { can } = useAbilities()
  return (
    <DocumentsSection
      resource="opportunity"
      id={opportunityId}
      canUpload={can('attachments.create')}
      canDelete={can('attachments.delete')}
    />
  )
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
          <DetailField label={t('opportunities.form.opportunityStatus')}>
            <Badge
              variant="secondary"
              className={cn(
                'h-5 min-h-5',
                opportunity.opportunity_status.color
                  ? OPPORTUNITY_STATUS_BADGE_CLASSES[opportunity.opportunity_status.color]
                  : undefined,
              )}
            >
              {opportunity.opportunity_status.name}
            </Badge>
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
          <DetailField label={t('opportunities.form.source')}>
            {opportunity.source?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.state')}>
            {opportunity.state?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('opportunities.form.workflowStatus')}>
            {opportunity.workflow_status ? (
              <Badge variant="secondary" className="h-5 min-h-5 gap-1.5">
                <span
                  className={cn(
                    'size-1.5 shrink-0 rounded-full',
                    swatchClassFor(opportunity.workflow_status.color) ?? 'bg-transparent',
                  )}
                  aria-hidden="true"
                />
                {opportunity.workflow_status.name}
              </Badge>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('opportunities.form.sections.productLines.title')} full>
            <ProductLinesList lines={opportunity.product_lines} />
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

      {opportunity.permissions.actions.view_documents ? (
        <DetailSection title={t('attachments.title')} icon={<Paperclip />}>
          <OpportunityDocumentsPanel opportunityId={opportunity.id} />
        </DetailSection>
      ) : null}

      {opportunity.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="opportunities" id={opportunity.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('opportunities.columns.createdAt')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
