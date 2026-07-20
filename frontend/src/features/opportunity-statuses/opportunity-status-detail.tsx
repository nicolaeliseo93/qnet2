import { useTranslation } from 'react-i18next'
import { Flag, History } from 'lucide-react'
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
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { OpportunityStatusDetailWithPermissions } from '@/features/opportunity-statuses/types'

interface OpportunityStatusDetailViewProps {
  opportunityStatus: OpportunityStatusDetailWithPermissions
}

/**
 * Read-only detail of a single opportunity status. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look.
 */
export function OpportunityStatusDetailView({ opportunityStatus }: OpportunityStatusDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(opportunityStatus.created_at)
  const swatch = swatchClassFor(opportunityStatus.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={opportunityStatus.name} icon={<Flag />} />}
        title={opportunityStatus.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('opportunityStatuses.detail.color')}>
            {opportunityStatus.color ? (
              <span className="flex items-center gap-2">
                <span
                  className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                  aria-hidden="true"
                />
                {t(`customFields.colors.${opportunityStatus.color}`)}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('opportunityStatuses.detail.sort_order')}>
            {opportunityStatus.sort_order}
          </DetailField>
          <DetailField label={t('opportunityStatuses.detail.group')}>
            {t(`opportunityStatuses.form.group.${opportunityStatus.group}`)}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {opportunityStatus.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="opportunity-statuses" id={opportunityStatus.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('opportunityStatuses.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
