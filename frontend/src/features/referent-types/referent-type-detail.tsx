import { useTranslation } from 'react-i18next'
import { History, Tag } from 'lucide-react'
import {
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { ReferentTypeDetailWithPermissions } from '@/features/referent-types/types'

interface ReferentTypeDetailViewProps {
  referentType: ReferentTypeDetailWithPermissions
}

/**
 * Read-only detail of a single referent type. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look
 * (mirrors `BusinessFunctionDetailView`).
 */
export function ReferentTypeDetailView({ referentType }: ReferentTypeDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(referentType.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={referentType.name} icon={<Tag />} />}
        title={referentType.name}
      />

      {referentType.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="referent-types" id={referentType.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('referentTypes.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
