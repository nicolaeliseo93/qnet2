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
import { WorkflowStatusOption } from '@/features/opportunity-workflows/workflow-status-option'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunityWorkflowStatusRef } from '@/features/opportunities/types'

interface OpportunityWorkflowStatusFieldProps {
  control: Control<OpportunityFormValues>
  /**
   * The resolved working-state set for THIS opportunity (spec 0047,
   * `OpportunityResource.workflow_statuses`). `null` = unknown yet (create
   * mode: the set depends on server-resolved criteria, only known once the
   * opportunity is saved) — the field is not rendered. An empty array
   * degrades the same way (defensive only: the backend always seeds at least
   * the 'open'/'closed_won'/'closed_lost' system rows).
   */
  statuses: OpportunityWorkflowStatusRef[] | null
}

/**
 * Spec 0047 (AC-026): a manual override of the currently resolved
 * "stato di lavorazione" (working-state), a NEW dimension distinct from
 * `opportunity_status_id` (sales pipeline). Limited to the set the backend
 * already resolved for this opportunity — never a remote for-select, a plain
 * shadcn `Select` over `statuses`. Edit mode only: on create the set is not
 * yet known (it depends on server-resolved criteria), so the caller passes
 * `statuses={null}` and this renders nothing.
 */
export function OpportunityWorkflowStatusField({ control, statuses }: OpportunityWorkflowStatusFieldProps) {
  const { t } = useTranslation()

  if (!statuses || statuses.length === 0) {
    return null
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
                <WorkflowStatusOption
                  name={status.name}
                  description={status.description}
                  color={status.color}
                  requiresNote={status.requires_note}
                />
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}
