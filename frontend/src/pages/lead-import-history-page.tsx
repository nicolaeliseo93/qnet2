import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { LeadImportsTable } from '@/features/imports/lead-imports-table'

/** Table/stats domain of the import module (the shared `import_runs` entity). */
const IMPORT_RUNS_DOMAIN = 'import-runs'

/**
 * Landing page of the Import module (spec 0034, AC-011): the dedicated
 * lead-import history, now treated as a standard module — stats toggle +
 * panel, a "New import" action, and the backend-driven history table
 * (export included automatically by `<TableView>`). Gated behind
 * `leads.import` (`<Can>`, UI-only — the backend re-checks the same
 * ability fail-closed on every table/stats/export endpoint of the domain).
 */
export default function LeadImportHistoryPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const stats = useStatsPanel(IMPORT_RUNS_DOMAIN)

  return (
    <Can
      permission="leads.import"
      fallback={<p className="text-sm text-muted-foreground">{t('leadImports.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader
          actions={
            <>
              <StatsToggleButton
                domain={IMPORT_RUNS_DOMAIN}
                isOpen={stats.isOpen}
                onToggle={stats.toggle}
              />
              <Button onClick={() => void navigate('/imports/new')}>
                <Plus aria-hidden="true" />
                {t('leadImports.newImport')}
              </Button>
            </>
          }
        />

        <ModuleStatsPanel domain={IMPORT_RUNS_DOMAIN} isOpen={stats.isOpen} />

        <LeadImportsTable />
      </div>
    </Can>
  )
}
