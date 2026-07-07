import { useTranslation } from 'react-i18next'
import { Tag as TagIcon } from 'lucide-react'
import {
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { TagDetail } from '@/features/tags/types'

interface TagDetailViewProps {
  tag: TagDetail
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

      {createdAt ? <DetailMeta label={t('tags.detail.created_at')}>{createdAt}</DetailMeta> : null}
    </DetailPanel>
  )
}
