import { CalendarClock } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { DurationInput } from '@/features/users/duration-input'
import { QUALIFICATION_TYPES, type QualificationType } from '@/features/users/types'
import type { UserFormValues } from '@/features/users/use-user-form'

/** Radix `Select` cannot hold an empty-string value: "no selection" uses this sentinel. */
const NONE_VALUE = '__none__'

interface ContractDataTabContentProps {
  control: Control<UserFormValues>
}

/** Contract-data tab: qualification, employment dates and daily durations. */
export function ContractDataTabContent({ control }: ContractDataTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={CalendarClock}
      title={t('users.form.sections.contractData.title')}
      description={t('users.form.sections.contractData.description')}
    >
      <MetaField
        control={control}
        name="employment.qualification_type"
        metaKey="employment.qualification_type"
        label={t('users.form.employment.qualificationType')}
      >
        {({ field, disabled }) => (
          <Select
            value={field.value ?? NONE_VALUE}
            onValueChange={(next) =>
              field.onChange(next === NONE_VALUE ? null : (next as QualificationType))
            }
            disabled={disabled}
          >
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem value={NONE_VALUE}>
                {t('users.form.employment.qualificationTypeNone')}
              </SelectItem>
              {QUALIFICATION_TYPES.map((type) => (
                <SelectItem key={type} value={type}>
                  {t(`enums.qualification_type.${type}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </MetaField>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <MetaField
          control={control}
          name="employment.hired_at"
          metaKey="employment.hired_at"
          label={t('users.form.employment.hiredAt')}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField
          control={control}
          name="employment.terminated_at"
          metaKey="employment.terminated_at"
          label={t('users.form.employment.terminatedAt')}
        >
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
          name="employment.standard_daily_minutes"
          metaKey="employment.standard_daily_minutes"
          label={t('users.form.employment.standardDailyMinutes')}
        >
          {({ field, disabled }) => (
            <FormControl>
              <DurationInput
                value={field.value}
                onChange={field.onChange}
                disabled={disabled}
                labels={{
                  hours: t('users.form.employment.hours'),
                  minutes: t('users.form.employment.minutes'),
                }}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField
          control={control}
          name="employment.break_daily_minutes"
          metaKey="employment.break_daily_minutes"
          label={t('users.form.employment.breakDailyMinutes')}
        >
          {({ field, disabled }) => (
            <FormControl>
              <DurationInput
                value={field.value}
                onChange={field.onChange}
                disabled={disabled}
                labels={{
                  hours: t('users.form.employment.hours'),
                  minutes: t('users.form.employment.minutes'),
                }}
              />
            </FormControl>
          )}
        </MetaField>
      </div>
    </FormSection>
  )
}
