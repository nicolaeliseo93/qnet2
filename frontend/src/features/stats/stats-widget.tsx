import { useTranslation } from 'react-i18next'
import { StatBarList } from '@/components/ui/stat-bar-list'
import { StatCard } from '@/components/ui/stat-card'
import { StatChart } from '@/components/ui/stat-chart'
import { formatStatValue, statSeriesFormatter } from '@/features/stats/format-stat-value'
import { formatTrendLabel } from '@/features/stats/format-trend-label'
import { resolveDistributionColor } from '@/features/stats/resolve-distribution-color'
import { resolveStatsIcon } from '@/features/stats/stats-icons'
import type { StatsWidget } from '@/features/stats/types'

interface StatsWidgetViewProps {
  widget: StatsWidget
}

/**
 * Renders one backend-described widget with the matching design-system
 * component. An unknown `type` renders nothing (AC-012): a backend newer than
 * the deployed frontend must never break the page.
 */
export function StatsWidgetView({ widget }: StatsWidgetViewProps) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language

  switch (widget.type) {
    case 'stat':
      return (
        <StatCard
          label={t(widget.label)}
          value={formatStatValue(widget.value, widget.format, locale)}
          subtitle={
            widget.subtitle ? t(widget.subtitle.key, { count: widget.subtitle.count }) : undefined
          }
          icon={resolveStatsIcon(widget.icon)}
        />
      )
    case 'distribution':
      return (
        <StatBarList
          title={t(widget.label)}
          items={widget.items.map((item) => ({
            ...item,
            color: resolveDistributionColor(item.color),
          }))}
          total={widget.total}
          formatValue={statSeriesFormatter('number', locale)}
          emptyLabel={t('statsPanel.noData')}
        />
      )
    case 'trend':
      return (
        <StatChart
          title={t(widget.label)}
          points={widget.points.map((point) => ({
            ...point,
            label: formatTrendLabel(point.label, locale),
          }))}
          formatValue={statSeriesFormatter(widget.format, locale)}
          emptyLabel={t('statsPanel.noData')}
        />
      )
    default:
      return null
  }
}
