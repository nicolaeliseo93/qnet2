import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
// Side effect: registers the `importWizard` i18next namespace (and this
// lane's `history.*` keys) before this page's own `t()` calls run.
import '@/features/imports/wizard/i18n'
import '@/features/imports/wizard/import-history-i18n'
import { ImportHistory } from '@/features/imports/wizard/import-history'

const LEADS_IMPORT_DOMAIN = 'leads'

/**
 * Lead import history page (spec 0033, AC-018): gated behind `leads.import`
 * (`<Can>`, UI-only — the backend re-checks the same ability fail-closed on
 * `GET /imports/{domain}`). All data-fetching lives in `ImportHistory`; this
 * page only supplies the chrome (breadcrumb, gate, i18n).
 */
export default function LeadImportHistoryPage() {
  const { t } = useTranslation('importWizard')

  return (
    <Can
      permission="leads.import"
      fallback={<p className="text-sm text-muted-foreground">{t('page.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader title={t('history.title')} subtitle={t('history.subtitle')} />
        <ImportHistory domain={LEADS_IMPORT_DOMAIN} />
      </div>
    </Can>
  )
}
