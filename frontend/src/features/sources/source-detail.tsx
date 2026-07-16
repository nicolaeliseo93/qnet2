import { useTranslation } from 'react-i18next'
import { Database, History } from 'lucide-react'
import {
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { SourceDetailWithPermissions } from '@/features/sources/types'

interface SourceDetailViewProps {
  source: SourceDetailWithPermissions
}

/**
 * Read-only detail of a single source. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down.
 * Composed from the shared detail kit for a consistent CRM look (mirrors
 * `ReferentTypeDetailView`).
 */
export function SourceDetailView({ source }: SourceDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(source.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={source.name} icon={<Database />} />}
        title={source.name}
      />

      {source.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="sources" id={source.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('sources.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
