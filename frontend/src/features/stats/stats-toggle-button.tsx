import { useTranslation } from 'react-i18next'
import { BarChart3 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { statsPanelId } from '@/features/stats/use-stats-panel'

interface StatsToggleButtonProps {
  /** Table domain of the module, e.g. `leads`. Ties the button to its panel. */
  domain: string
  isOpen: boolean
  onToggle: () => void
}

/**
 * Opens/closes a module's statistics panel. Lives in the `actions` of the
 * `PageHeader`, right before the module's "New {entity}" button, and is
 * identical in every module (AC-006). Icon-only: the accessible name comes
 * from `aria-label` (never from the tooltip, which is not reliably reachable
 * by assistive tech), while the tooltip surface gives sighted mouse users a
 * hint that also reflects the current state.
 */
export function StatsToggleButton({ domain, isOpen, onToggle }: StatsToggleButtonProps) {
  const { t } = useTranslation()

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            type="button"
            variant="outline"
            size="icon"
            onClick={onToggle}
            aria-label={t('statsPanel.toggle')}
            aria-expanded={isOpen}
            aria-controls={statsPanelId(domain)}
          >
            <BarChart3 aria-hidden="true" />
          </Button>
        </TooltipTrigger>
        <TooltipContent>{t(isOpen ? 'statsPanel.hide' : 'statsPanel.show')}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
