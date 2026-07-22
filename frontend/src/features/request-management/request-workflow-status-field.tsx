import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { WorkflowStatusOption } from '@/features/opportunity-workflows/workflow-status-option'
import { cn } from '@/lib/utils'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestWorkflowStatusRef } from '@/features/request-management/types'

interface RequestWorkflowStatusFieldProps {
  control: Control<RequestWorkFormValues>
  /** The resolved working-state set for this opportunity (spec 0047/0049 AC-063): the select never offers a value outside it. */
  statuses: RequestWorkflowStatusRef[]
}

/** A status option's leading color dot, shared with the read-only context summary. */
export function WorkflowStatusSwatch({ color }: { color: string | null }) {
  return (
    <span
      className={cn('size-2.5 shrink-0 rounded-full border', swatchClassFor(color) ?? 'bg-transparent')}
      aria-hidden="true"
    />
  )
}

/**
 * Spec 0049 (AC-063): the operator's only lever over the working state
 * (`opportunity_workflow_status_id`) — a plain shadcn `Select` limited to the
 * `workflow_statuses` set the backend already resolved for this opportunity,
 * so an out-of-set value is unreachable from the UI (transitions stay free
 * within the set, spec 0047). Renders nothing when the resolved set is empty
 * (defensive only: the backend always seeds at least the system rows).
 */
export function RequestWorkflowStatusField({ control, statuses }: RequestWorkflowStatusFieldProps) {
  const { t } = useTranslation()

  if (statuses.length === 0) {
    return null
  }

  return (
    <MetaField
      control={control}
      name="opportunity_workflow_status_id"
      metaKey="opportunity_workflow_status_id"
      label={t('requestManagement.workPanel.workflowStatus.label', { defaultValue: 'Working status' })}
    >
      {({ field, disabled }) => (
        <Select
          value={field.value !== null ? String(field.value) : undefined}
          onValueChange={(next) => field.onChange(Number(next))}
          disabled={disabled}
        >
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue
                placeholder={t('requestManagement.workPanel.workflowStatus.placeholder', {
                  defaultValue: 'Select a status',
                })}
              />
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
