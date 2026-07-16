import { useTranslation } from 'react-i18next'
import { History, Tag as TagIcon } from 'lucide-react'
import {
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import type { TagDetailWithPermissions } from '@/features/tags/types'

interface TagDetailViewProps {
  tag: TagDetailWithPermissions
}

/**
 * Read-only detail of a single tag. Purely presentational: the caller (the
 * table's "view" sheet) fetches the fresh detail and passes it down.
 * Composed from the shared detail kit for a consistent CRM look (mirrors
 * `ReferentTypeDetailView`).
 */
export function TagDetailView({ tag }: TagDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(tag.created_at)

  return (
    <DetailPanel>
      <DetailHero media={<DetailMonogram name={tag.name} icon={<TagIcon />} />} title={tag.name} />

      {tag.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="tags" id={tag.id} />
        </DetailSection>
      ) : null}

      {createdAt ? <DetailMeta label={t('tags.detail.created_at')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
