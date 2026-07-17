import { useTranslation } from 'react-i18next'
import { History, Percent } from 'lucide-react'
import {
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
import { formatRate } from '@/features/vat-rates/column-renderers'
import type { VatRateDetailWithPermissions } from '@/features/vat-rates/types'

interface VatRateDetailViewProps {
  vatRate: VatRateDetailWithPermissions
}

/**
 * Read-only detail of a single VAT rate. Purely presentational: the caller
 * (the table's "view" sheet) fetches the fresh detail and passes it down.
 * Composed from the shared detail kit for a consistent CRM look (mirrors
 * `SourceDetailView`).
 */
export function VatRateDetailView({ vatRate }: VatRateDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(vatRate.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={vatRate.name} icon={<Percent />} />}
        title={vatRate.name}
      />

      <DetailSection title={t('vatRates.detail.details')}>
        <DetailGrid>
          <DetailField label={t('vatRates.columns.rate')}>{formatRate(vatRate.rate)}%</DetailField>
        </DetailGrid>
      </DetailSection>

      {vatRate.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="vat-rates" id={vatRate.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('vatRates.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
