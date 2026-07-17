import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { VatRatesTable } from '@/features/vat-rates/vat-rates-table'

/**
 * VAT rates page. Light composition only: gates access with
 * `vat-rates.viewAny` and mounts the thin VAT rates adapter, which in turn
 * mounts the generic table (`domain="vat-rates"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here (mirrors `SourcesPage`).
 */
export default function VatRatesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="vat-rates.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('vatRates.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <VatRatesTable />
      </div>
    </Can>
  )
}
