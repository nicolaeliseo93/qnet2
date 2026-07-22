import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { formatDecimal } from '@/features/products/column-renderers'
import { WorkflowStatusSwatch } from '@/features/request-management/request-workflow-status-field'
import type { RequestWorkPanel } from '@/features/request-management/types'

interface RequestWorkContextProps {
  panel: RequestWorkPanel
}

/** Formats a `Y-m-d` date for display, blank when missing/invalid (mirrors `opportunity-detail.tsx`'s own helper). */
function formatDate(value: string | null): string | null {
  if (!value) {
    return null
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString()
}

/** One `label: value` pair of the header strip. */
function ContextItem({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex min-w-0 items-baseline gap-1.5">
      <dt className="shrink-0 text-xs text-muted-foreground">{label}</dt>
      <dd className="truncate text-xs font-medium text-foreground">{children}</dd>
    </div>
  )
}

/**
 * Read-only commercial context of the record (spec 0049), reduced to a COMPACT
 * header strip: the page is a data-entry workstation, so this never competes
 * for space with the editable sections below. Never renders
 * `opportunity_status_id`'s edit control — that dimension stays the CRUD
 * opportunities form's job (D-5).
 */
export function RequestWorkContext({ panel }: RequestWorkContextProps) {
  const { t } = useTranslation()
  const expectedCloseDate = formatDate(panel.context.expected_close_date)
  const estimatedValue = formatDecimal(panel.context.estimated_value)

  return (
    <header className="flex flex-col gap-2 rounded-lg border bg-card px-3 py-2.5 shadow-sm">
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
        <h2 className="min-w-0 truncate text-sm font-semibold">{panel.name}</h2>
        {panel.opportunity_status && (
          <Badge variant="secondary" className="h-5 min-h-5 gap-1.5">
            <WorkflowStatusSwatch color={panel.opportunity_status.color} />
            {panel.opportunity_status.name}
          </Badge>
        )}
      </div>

      <dl className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <ContextItem label={t('requestManagement.workPanel.context.registry', { defaultValue: 'Registry' })}>
          {panel.registry?.name ?? '—'}
        </ContextItem>
        <ContextItem
          label={t('requestManagement.workPanel.context.expectedCloseDate', {
            defaultValue: 'Expected close date',
          })}
        >
          {expectedCloseDate ?? '—'}
        </ContextItem>
        <ContextItem
          label={t('requestManagement.workPanel.context.estimatedValue', { defaultValue: 'Estimated value' })}
        >
          {estimatedValue || '—'}
        </ContextItem>
        <ContextItem
          label={t('requestManagement.workPanel.context.productLines', { defaultValue: 'Product lines' })}
        >
          {panel.product_lines.length === 0
            ? t('common.none', { defaultValue: 'None' })
            : panel.product_lines
                .map((line) => `${line.business_function.name} — ${line.product_category.name}`)
                .join(' · ')}
        </ContextItem>
      </dl>
    </header>
  )
}
