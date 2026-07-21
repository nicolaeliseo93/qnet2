import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { OpportunityWorkflowsTable } from '@/features/opportunity-workflows/opportunity-workflows-table'

/**
 * Opportunity workflow configurator page (spec 0047 Lane C). Light
 * composition only: gates access with `opportunity-workflows.viewAny` and
 * mounts the thin Opportunity Workflows adapter, which in turn mounts the
 * generic table (`domain="opportunity-workflows"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here.
 */
export default function OpportunityWorkflowsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="opportunity-workflows.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('opportunityWorkflows.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <OpportunityWorkflowsTable />
      </div>
    </Can>
  )
}
