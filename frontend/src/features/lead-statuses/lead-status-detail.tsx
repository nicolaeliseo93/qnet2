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
import type { LeadStatusDetailWithPermissions } from '@/features/lead-statuses/types'

interface LeadStatusDetailViewProps {
  leadStatus: LeadStatusDetailWithPermissions
}

/**
 * Read-only detail of a single lead status. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `PipelineStatusDetailView`).
 */
export function LeadStatusDetailView({ leadStatus }: LeadStatusDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(leadStatus.created_at)
  const swatch = swatchClassFor(leadStatus.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={leadStatus.name} icon={<Flag />} />}
        title={leadStatus.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('leadStatuses.detail.color')}>
            {leadStatus.color ? (
              <span className="flex items-center gap-2">
                <span
                  className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                  aria-hidden="true"
                />
                {t(`customFields.colors.${leadStatus.color}`)}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('leadStatuses.detail.sort_order')}>
            {leadStatus.sort_order}
          </DetailField>
          <DetailField label={t('leadStatuses.detail.group')}>
            {t(`leadStatuses.form.group.${leadStatus.group}`)}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {leadStatus.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="lead-statuses" id={leadStatus.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('leadStatuses.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
