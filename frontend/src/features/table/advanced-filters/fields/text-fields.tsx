import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import type { AdvancedFilterFieldProps } from '@/features/table/advanced-filters/advanced-filter-field-props'
import type { AdvancedFilterRange } from '@/features/table/advanced-filters/types'

/** `type: 'text'` -> a single-line `Input`. */
export function TextAdvancedFilterField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  return (
    <Input
      id={id}
      type="text"
      disabled={disabled}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      placeholder={descriptor.placeholder ? t(descriptor.placeholder) : undefined}
      value={typeof value === 'string' ? value : ''}
      onChange={(event) => onChange(event.target.value)}
    />
  )
}

/** `type: 'textarea'` -> a multi-line `Textarea`. */
export function TextareaAdvancedFilterField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  return (
    <Textarea
      id={id}
      disabled={disabled}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      placeholder={descriptor.placeholder ? t(descriptor.placeholder) : undefined}
      value={typeof value === 'string' ? value : ''}
      onChange={(event) => onChange(event.target.value)}
    />
  )
}

/** `type: 'number'` -> a numeric `Input`. */
export function NumberAdvancedFilterField({
  descriptor,
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  return (
    <Input
      id={id}
      type="number"
      disabled={disabled}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      placeholder={descriptor.placeholder ? t(descriptor.placeholder) : undefined}
      value={typeof value === 'number' ? value : ''}
      onChange={(event) => {
        const raw = event.target.value
        onChange(raw === '' ? null : Number.parseFloat(raw))
      }}
    />
  )
}

/** `type: 'number_range'` -> two numeric `Input`s (from/to), combined into `{from?, to?}`. */
export function NumberRangeAdvancedFilterField({
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const range = (value ?? {}) as AdvancedFilterRange<number>

  const setBound = (bound: 'from' | 'to', raw: string) => {
    onChange({ ...range, [bound]: raw === '' ? undefined : Number.parseFloat(raw) })
  }

  return (
    <div className="flex items-center gap-2">
      <Input
        id={id}
        type="number"
        disabled={disabled}
        aria-describedby={describedBy}
        aria-invalid={invalid}
        aria-label={t('table.advancedFilters.rangeFrom')}
        placeholder={t('table.advancedFilters.rangeFrom')}
        value={range.from ?? ''}
        onChange={(event) => setBound('from', event.target.value)}
      />
      <span className="text-xs text-muted-foreground" aria-hidden="true">
        {t('table.advancedFilters.rangeSeparator')}
      </span>
      <Input
        type="number"
        disabled={disabled}
        aria-label={t('table.advancedFilters.rangeTo')}
        placeholder={t('table.advancedFilters.rangeTo')}
        value={range.to ?? ''}
        onChange={(event) => setBound('to', event.target.value)}
      />
    </div>
  )
}
