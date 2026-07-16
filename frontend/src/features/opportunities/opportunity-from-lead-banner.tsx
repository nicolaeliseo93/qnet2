import { useTranslation } from 'react-i18next'
import { Workflow } from 'lucide-react'

interface OpportunityFromLeadBannerProps {
  /** The originating lead's identity — its referent name (D-3: a lead has no name of its own). */
  referentName: string | null
}

/**
 * Compact banner shown at the top of the create form when creating an
 * Opportunity from a Lead (spec 0040 MT-6, `/opportunities/new?lead_id=N`):
 * states the origin so the locked fields below aren't a silent surprise.
 * `role="status"` (not `alert`): informational, not an error.
 */
export function OpportunityFromLeadBanner({ referentName }: OpportunityFromLeadBannerProps) {
  const { t } = useTranslation()

  return (
    <div
      role="status"
      className="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-foreground"
    >
      <Workflow className="size-4 shrink-0 text-primary" aria-hidden="true" />
      {referentName
        ? t('opportunities.form.fromLeadBannerNamed', { name: referentName })
        : t('opportunities.form.fromLeadBanner')}
    </div>
  )
}
