import { useTranslation } from 'react-i18next'
import { PhoneCall } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'

interface RequestCallbackSectionProps {
  control: Control<RequestWorkFormValues>
}

/**
 * The operator's next scheduled follow-up call (spec 0052 D-1/D-5): a single
 * `datetime-local` input PATCHed sparse via `next_callback_at`. `null` clears
 * the plan. Read-only users get the control disabled, same `MetaField`
 * gating as every other field of this panel (AC-008).
 */
export function RequestCallbackSection({ control }: RequestCallbackSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={PhoneCall}
      title={t('requestManagement.workPanel.callback.title', { defaultValue: 'Next callback' })}
      description={t('requestManagement.workPanel.callback.description', {
        defaultValue: 'Plan the next follow-up call with the client.',
      })}
    >
      <MetaField
        control={control}
        name="next_callback_at"
        metaKey="next_callback_at"
        label={t('requestManagement.workPanel.callback.label', { defaultValue: 'Next callback' })}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input
              type="datetime-local"
              disabled={disabled}
              readOnly={readOnly}
              placeholder={t('requestManagement.workPanel.callback.placeholder', {
                defaultValue: 'Select date and time',
              })}
              value={field.value ?? ''}
              onChange={(event) => field.onChange(event.target.value || null)}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}
