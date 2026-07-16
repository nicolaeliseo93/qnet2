import { useTranslation } from 'react-i18next'
import { History, ListTree } from 'lucide-react'
import {
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { SectorDetailWithPermissions } from '@/features/sectors/types'

interface SectorDetailViewProps {
  sector: SectorDetailWithPermissions
}

/**
 * Read-only detail of a single sector. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down
 * (mirrors `ReferentTypeDetailView`).
 */
export function SectorDetailView({ sector }: SectorDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(sector.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={sector.name} icon={<ListTree />} />}
        title={sector.name}
        subtitle={sector.parent?.name}
      />

      {sector.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="sectors" id={sector.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('sectors.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
