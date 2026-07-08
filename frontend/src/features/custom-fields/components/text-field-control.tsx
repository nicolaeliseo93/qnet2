import { Input } from '@/components/ui/input'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'

/** `type: 'text'` → a single-line `Input`, bounded by `config.maxLength`. */
export function TextFieldControl({
  descriptor,
  value,
  onChange,
  disabled,
  readOnly,
  id,
  describedBy,
  invalid,
}: CustomFieldControlProps) {
  return (
    <Input
      id={id}
      type="text"
      disabled={disabled}
      readOnly={readOnly}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      placeholder={descriptor.placeholder ?? undefined}
      maxLength={descriptor.config?.maxLength}
      value={typeof value === 'string' ? value : ''}
      onChange={(event) => onChange(event.target.value)}
    />
  )
}
