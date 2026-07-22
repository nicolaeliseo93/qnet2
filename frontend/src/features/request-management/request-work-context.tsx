import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'
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

function ContextField({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="text-sm text-foreground">{children}</dd>
    </div>
  )
}

/** Read-only list of the opportunity's business-function + product-category rows. */
function ProductLinesList({ panel }: { panel: RequestWorkPanel }) {
  const { t } = useTranslation()
  if (panel.product_lines.length === 0) {
    return <span className="text-sm text-muted-foreground">{t('common.none', { defaultValue: 'None' })}</span>
  }
  return (
    <ul className="flex flex-wrap gap-1.5">
      {panel.product_lines.map((line) => (
        <li key={line.id}>
          <Badge variant="outline" className="font-normal">
            {line.business_function.name} — {line.product_category.name}
          </Badge>
        </li>
      ))}
    </ul>
  )
}

/**
 * Read-only commercial context of the record (spec 0049): name, registry,
 * sales-pipeline status (never editable here, spec D-5), expected close date,
 * estimated value and product lines. Never renders `opportunity_status_id`'s
 * edit control — that dimension stays the CRUD opportunities form's job.
 */
export function RequestWorkContext({ panel }: RequestWorkContextProps) {
  const { t } = useTranslation()
  const expectedCloseDate = formatDate(panel.context.expected_close_date)
  const estimatedValue = formatDecimal(panel.context.estimated_value)

  return (
    <FormSection
      icon={Building2}
      title={panel.name}
      description={t('requestManagement.workPanel.context.subtitle', {
        defaultValue: 'Opportunity context (read-only).',
      })}
    >
      <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <ContextField label={t('requestManagement.workPanel.context.registry', { defaultValue: 'Registry' })}>
          {panel.registry?.name ?? '—'}
        </ContextField>
        <ContextField
          label={t('requestManagement.workPanel.context.opportunityStatus', { defaultValue: 'Sales status' })}
        >
          {panel.opportunity_status ? (
            <Badge variant="secondary" className="h-5 min-h-5 gap-1.5">
              <WorkflowStatusSwatch color={panel.opportunity_status.color} />
              {panel.opportunity_status.name}
            </Badge>
          ) : (
            '—'
          )}
        </ContextField>
        <ContextField
          label={t('requestManagement.workPanel.context.expectedCloseDate', {
            defaultValue: 'Expected close date',
          })}
        >
          {expectedCloseDate ?? '—'}
        </ContextField>
        <ContextField
          label={t('requestManagement.workPanel.context.estimatedValue', { defaultValue: 'Estimated value' })}
        >
          {estimatedValue || '—'}
        </ContextField>
        <div className="flex flex-col gap-1 sm:col-span-2">
          <dt className="text-xs font-medium text-muted-foreground">
            {t('requestManagement.workPanel.context.productLines', { defaultValue: 'Product lines' })}
          </dt>
          <dd>
            <ProductLinesList panel={panel} />
          </dd>
        </div>
      </dl>
    </FormSection>
  )
}
