import { useTranslation } from 'react-i18next'
import { ListTree } from 'lucide-react'
import { DetailHero, DetailMeta, DetailMonogram, DetailPanel } from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { EaSectorDetail } from '@/features/ea-sectors/types'

interface EaSectorDetailViewProps {
  sector: EaSectorDetail
}

/**
 * Read-only detail of a single EA sector. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down
 * (mirrors `ReferentTypeDetailView`).
 */
export function EaSectorDetailView({ sector }: EaSectorDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(sector.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={sector.name} icon={<ListTree />} />}
        title={sector.name}
        subtitle={sector.parent?.name}
      />

      {createdAt ? (
        <DetailMeta label={t('eaSectors.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
