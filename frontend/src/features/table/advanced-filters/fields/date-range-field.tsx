import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import type { AdvancedFilterFieldProps } from '@/features/table/advanced-filters/advanced-filter-field-props'
import type { AdvancedFilterRange } from '@/features/table/advanced-filters/types'

/** `type: 'date_range'` -> two `Input type="date"` (from/to), combined into `{from?, to?}` ISO dates. */
export function DateRangeAdvancedFilterField({
  value,
  onChange,
  disabled,
  id,
  describedBy,
  invalid,
}: AdvancedFilterFieldProps) {
  const { t } = useTranslation()
  const range = (value ?? {}) as AdvancedFilterRange<string>

  const setBound = (bound: 'from' | 'to', raw: string) => {
    onChange({ ...range, [bound]: raw === '' ? undefined : raw })
  }

  return (
    <div className="flex items-center gap-2">
      <Input
        id={id}
        type="date"
        disabled={disabled}
        aria-describedby={describedBy}
        aria-invalid={invalid}
        aria-label={t('table.advancedFilters.rangeFrom')}
        value={range.from ?? ''}
        onChange={(event) => setBound('from', event.target.value)}
      />
      <span className="text-xs text-muted-foreground" aria-hidden="true">
        {t('table.advancedFilters.rangeSeparator')}
      </span>
      <Input
        type="date"
        disabled={disabled}
        aria-label={t('table.advancedFilters.rangeTo')}
        value={range.to ?? ''}
        onChange={(event) => setBound('to', event.target.value)}
      />
    </div>
  )
}
