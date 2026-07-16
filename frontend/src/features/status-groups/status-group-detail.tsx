import { useTranslation } from 'react-i18next'
import { History, Shapes } from 'lucide-react'
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
import type { StatusGroupDetailWithPermissions } from '@/features/status-groups/types'

interface StatusGroupDetailViewProps {
  statusGroup: StatusGroupDetailWithPermissions
}

/**
 * Read-only detail of a single status group. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `LeadStatusDetailView`).
 */
export function StatusGroupDetailView({ statusGroup }: StatusGroupDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(statusGroup.created_at)
  const swatch = swatchClassFor(statusGroup.color)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={statusGroup.name} icon={<Shapes />} />}
        title={statusGroup.name}
      />

      <DetailSection>
        <DetailGrid>
          <DetailField label={t('statusGroups.detail.color')}>
            {statusGroup.color ? (
              <span className="flex items-center gap-2">
                <span
                  className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                  aria-hidden="true"
                />
                {t(`customFields.colors.${statusGroup.color}`)}
              </span>
            ) : (
              <DetailEmpty />
            )}
          </DetailField>
          <DetailField label={t('statusGroups.detail.sort_order')}>
            {statusGroup.sort_order}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {statusGroup.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="status-groups" id={statusGroup.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('statusGroups.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
