import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import type { AdvancedFilterFieldProps } from '@/features/table/advanced-filters/advanced-filter-field-props'

/**
 * Factory for the native date-backed inputs: `type: 'date'` and `type:
 * 'datetime'` differ only by HTML `type`. Called once per field type while
 * the registry object is built (module scope, in `field-registry.tsx`), so
 * every returned component has a stable identity across renders — never
 * define these inside another component (frontend.md §10). Kept in its own,
 * factory-only file (mirrors `custom-fields/native-input-field-control.tsx`):
 * a file mixing a factory export with a component export trips
 * `react-refresh/only-export-components`.
 */
export function createDateAdvancedFilterField(htmlType: 'date' | 'datetime-local') {
  return function DateBackedAdvancedFilterField({
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
        type={htmlType}
        disabled={disabled}
        aria-describedby={describedBy}
        aria-invalid={invalid}
        placeholder={descriptor.placeholder ? t(descriptor.placeholder) : undefined}
        value={typeof value === 'string' ? value : ''}
        onChange={(event) => onChange(event.target.value)}
      />
    )
  }
}
