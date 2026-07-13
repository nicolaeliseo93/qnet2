import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { CalendarRange } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl, FormDescription } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { formatDecimal } from '@/features/products/column-renderers'
import { useCampaignProjectMeta } from '@/features/campaigns/use-campaign-project-meta'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'

interface CampaignPlanningSectionProps {
  control: Control<CampaignFormValues>
  /** The currently linked project's id (watched from the form), or `null` when standalone. */
  projectId: number | null
}

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * BR-3 hint shown next to the budget field while the campaign is linked to a
 * project with a budget cap: the project's current residual, so the user
 * knows the ceiling BEFORE hitting the 422. Display-only — never gates the
 * submit, which stays the backend's authority.
 */
function RemainingBudgetHint({ projectId }: { projectId: number | null }) {
  const { t } = useTranslation()
  const { data: meta } = useCampaignProjectMeta(projectId)

  if (projectId === null || !meta || meta.remaining_budget === null) {
    return null
  }
  return (
    <FormDescription>
      {t('campaigns.form.projectRemainingBudget', { amount: formatDecimal(meta.remaining_budget) })}
    </FormDescription>
  )
}

/**
 * The campaign's planning fields (dates, budget, target leads): extracted so
 * `CampaignFormBody` stays within the engineering size limits (spec 0023,
 * mirrors why `ProjectRelationField` was split out of `ProjectFormBody`).
 */
export function CampaignPlanningSection({ control, projectId }: CampaignPlanningSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={CalendarRange}
      title={t('campaigns.form.sections.planning.title')}
      description={t('campaigns.form.sections.planning.description')}
    >
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <MetaField control={control} name="start_date" metaKey="start_date" label={t('campaigns.form.startDate')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="end_date" metaKey="end_date" label={t('campaigns.form.endDate')}>
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
          label={t('campaigns.form.totalBudget')}
          description={<RemainingBudgetHint projectId={projectId} />}
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

        <MetaField control={control} name="target_lead" metaKey="target_lead" label={t('campaigns.form.targetLead')}>
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
