import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunityWorkflowStatusRef } from '@/features/opportunities/types'

interface OpportunityWorkflowStatusFieldProps {
  control: Control<OpportunityFormValues>
  /**
   * The resolved working-state set for THIS opportunity (spec 0047,
   * `OpportunityResource.workflow_statuses`). `null` = unknown yet (create
   * mode: the set depends on server-resolved criteria, only known once the
   * opportunity is saved) — renders an explanatory hint instead of the
   * select. An empty array degrades the same way (defensive only: the
   * backend always seeds at least the 'open'/'closed' system rows).
   */
  statuses: OpportunityWorkflowStatusRef[] | null
}

/** A status option's leading color dot, mirrors the read-only detail's swatch (`OpportunityWorkflowDetailView`). */
function StatusSwatch({ color }: { color: string | null }) {
  return (
    <span
      className={cn('size-2.5 shrink-0 rounded-full border', swatchClassFor(color) ?? 'bg-transparent')}
      aria-hidden="true"
    />
  )
}

/**
 * Spec 0047 (AC-026): a manual override of the currently resolved
 * "stato di lavorazione" (working-state), a NEW dimension distinct from
 * `opportunity_status_id` (sales pipeline). Limited to the set the backend
 * already resolved for this opportunity — never a remote for-select, a plain
 * shadcn `Select` over `statuses`. Edit mode only: on create the set is not
 * yet known (it depends on server-resolved criteria), so the caller passes
 * `statuses={null}` and this renders a hint instead.
 */
export function OpportunityWorkflowStatusField({ control, statuses }: OpportunityWorkflowStatusFieldProps) {
  const { t } = useTranslation()

  if (!statuses || statuses.length === 0) {
    return (
      <p className="self-end pb-2 text-xs text-muted-foreground">
        {t('opportunities.form.workflowStatusPendingHint')}
      </p>
    )
  }

  return (
    <MetaField
      control={control}
      name="opportunity_workflow_status_id"
      metaKey="opportunity_workflow_status_id"
      label={t('opportunities.form.workflowStatus')}
      hint={t('opportunities.form.workflowStatusHint')}
    >
      {({ field, disabled }) => (
        <Select
          value={field.value !== null ? String(field.value) : undefined}
          onValueChange={(next) => field.onChange(Number(next))}
          disabled={disabled}
        >
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue placeholder={t('opportunities.form.selectPlaceholder')} />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {statuses.map((status) => (
              <SelectItem key={status.id} value={String(status.id)}>
                <span className="flex items-center gap-2">
                  <StatusSwatch color={status.color} />
                  {status.name}
                </span>
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}
