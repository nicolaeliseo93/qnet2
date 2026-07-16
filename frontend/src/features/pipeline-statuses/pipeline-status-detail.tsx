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
import type { PipelineStatusDetailWithPermissions } from '@/features/pipeline-statuses/types'

interface PipelineStatusDetailViewProps {
  pipelineStatus: PipelineStatusDetailWithPermissions
}

/**
 * Read-only detail of a single project status. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `SourceDetailView`).
 */
export function PipelineStatusDetailView({ pipelineStatus }: PipelineStatusDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(pipelineStatus.created_at)
  const swatch = swatchClassFor(pipelineStatus.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={pipelineStatus.name} icon={<Flag />} />}
        title={pipelineStatus.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('pipelineStatuses.detail.color')}>
            {pipelineStatus.color ? (
              <span className="flex items-center gap-2">
                <span
                  className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                  aria-hidden="true"
                />
                {t(`customFields.colors.${pipelineStatus.color}`)}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('pipelineStatuses.detail.sort_order')}>
            {pipelineStatus.sort_order}
          </DetailField>
          <DetailField label={t('pipelineStatuses.detail.status_group')}>
            {pipelineStatus.status_group ? pipelineStatus.status_group.name : <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {pipelineStatus.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="pipeline-statuses" id={pipelineStatus.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('pipelineStatuses.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
