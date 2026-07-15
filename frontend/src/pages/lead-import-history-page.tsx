import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { LeadImportsTable } from '@/features/imports/lead-imports-table'

/**
 * Lead import history page (spec 0033, AC-018): gated behind `leads.import`
 * (`<Can>`, UI-only — the backend re-checks the same ability fail-closed on
 * the generic table endpoints). The history is now the standard backend-driven
 * AG Grid table (`domain="lead-imports"`); this page only supplies the chrome
 * (breadcrumb, gate) while `LeadImportsTable` mounts the generic table.
 */
export default function LeadImportHistoryPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="leads.import"
      fallback={<p className="text-sm text-muted-foreground">{t('leadImports.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader title={t('leadImports.title')} subtitle={t('leadImports.subtitle')} />
        <LeadImportsTable />
      </div>
    </Can>
  )
}
