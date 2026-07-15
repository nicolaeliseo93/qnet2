import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
// Side effect: registers the `importWizard` i18next namespace before this
// page's own `t()` calls run (see `features/imports/wizard/i18n.ts`).
import '@/features/imports/wizard/i18n'
import { ImportWizard } from '@/features/imports/wizard/import-wizard'

const LEADS_IMPORT_DOMAIN = 'leads'

/**
 * Advanced lead import wizard page (spec 0033, AC-026): gated behind
 * `leads.import` (`<Can>`, UI-only — the backend re-checks the same ability
 * fail-closed on every endpoint). All orchestration lives in `ImportWizard`;
 * this page only supplies the chrome (breadcrumb, gate, i18n).
 */
export default function LeadImportPage() {
  const { t } = useTranslation('importWizard')

  return (
    <Can
      permission="leads.import"
      fallback={<p className="text-sm text-muted-foreground">{t('page.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader title={t('page.title')} subtitle={t('page.subtitle')} />
        <ImportWizard domain={LEADS_IMPORT_DOMAIN} />
      </div>
    </Can>
  )
}
