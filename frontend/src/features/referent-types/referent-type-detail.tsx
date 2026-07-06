import { useTranslation } from 'react-i18next'
import { Tag } from 'lucide-react'
import {
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { ReferentTypeDetail } from '@/features/referent-types/types'

interface ReferentTypeDetailViewProps {
  referentType: ReferentTypeDetail
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

      {createdAt ? (
        <DetailMeta label={t('referentTypes.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
