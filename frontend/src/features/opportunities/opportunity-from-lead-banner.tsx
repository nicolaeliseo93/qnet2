import { useTranslation } from 'react-i18next'
import { Workflow } from 'lucide-react'

interface OpportunityFromLeadBannerProps {
  /** The originating lead's identity — its anagrafica name (spec 0041 D-3: a lead has no name of its own). */
  registryName: string | null
}

/**
 * Compact banner shown at the top of the create form when creating an
 * Opportunity from a Lead (spec 0040 MT-6, `/opportunities/new?lead_id=N`):
 * states the origin so the locked fields below aren't a silent surprise.
 * `role="status"` (not `alert`): informational, not an error.
 */
export function OpportunityFromLeadBanner({ registryName }: OpportunityFromLeadBannerProps) {
  const { t } = useTranslation()

  return (
    <div
      role="status"
      className="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-foreground"
    >
      <Workflow className="size-4 shrink-0 text-primary" aria-hidden="true" />
      {registryName
        ? t('opportunities.form.fromLeadBannerNamed', { name: registryName })
        : t('opportunities.form.fromLeadBanner')}
    </div>
  )
}
