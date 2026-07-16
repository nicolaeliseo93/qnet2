import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormState, type Control } from 'react-hook-form'
import { CalendarRange } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import type { ProjectFormValues } from '@/features/projects/use-project-form'

interface ProjectPlanningSectionProps {
  control: Control<ProjectFormValues>
}

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/** Staggered mount reveal (motion-safe), third of the four cascading sections. */
const SECTION_REVEAL_PLANNING =
  'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:fill-mode-both motion-safe:duration-300 motion-safe:delay-200'

/**
 * The project's planning fields (dates, budget, target leads): a secondary,
 * all-optional group — collapsed by default and force-opened when one of
 * its own fields carries a validation error, so the message stays reachable
 * without an effect (reading `formState.errors` in render already
 * subscribes). Extracted so `ProjectFormBody` stays within the engineering
 * size limits (spec 0023 precedent: `CampaignPlanningSection`,
 * `ProjectGeographySection`).
 */
export function ProjectPlanningSection({ control }: ProjectPlanningSectionProps) {
  const { t } = useTranslation()
  const { errors } = useFormState({ control })
  const [open, setOpen] = useState(false)
  const hasError = Boolean(
    errors.start_date || errors.end_date || errors.total_budget || errors.target_lead,
  )

  return (
    <FormSection
      icon={CalendarRange}
      title={t('projects.form.sections.planning.title')}
      description={t('projects.form.sections.planning.description')}
      collapsible
      defaultOpen={false}
      open={open || hasError}
      onOpenChange={setOpen}
      className={SECTION_REVEAL_PLANNING}
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <MetaField control={control} name="start_date" metaKey="start_date" label={t('projects.form.startDate')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="end_date" metaKey="end_date" label={t('projects.form.endDate')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <MetaField
          control={control}
          name="total_budget"
          metaKey="total_budget"
          label={t('projects.form.totalBudget')}
          hint={t('projects.form.hints.totalBudget')}
          hintLabel={t('projects.form.totalBudget')}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                step="0.01"
                disabled={disabled}
                readOnly={readOnly}
                value={numberInputValue(field.value)}
                onChange={(event) =>
                  field.onChange(event.target.value === '' ? null : Number(event.target.value))
                }
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField
          control={control}
          name="target_lead"
          metaKey="target_lead"
          label={t('projects.form.targetLead')}
          hint={t('projects.form.hints.targetLead')}
          hintLabel={t('projects.form.targetLead')}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                step="1"
                disabled={disabled}
                readOnly={readOnly}
                value={numberInputValue(field.value)}
                onChange={(event) =>
                  field.onChange(event.target.value === '' ? null : Number(event.target.value))
                }
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
          )}
        </MetaField>
      </div>
    </FormSection>
  )
}
