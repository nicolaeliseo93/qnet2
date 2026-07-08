import { Textarea } from '@/components/ui/textarea'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'

/** `type: 'textarea'` → a multi-line `Textarea`, bounded by `config.maxLength`/`config.rows`. */
export function TextareaFieldControl({
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
    <Textarea
      id={id}
      disabled={disabled}
      readOnly={readOnly}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      placeholder={descriptor.placeholder ?? undefined}
      maxLength={descriptor.config?.maxLength}
      rows={descriptor.config?.rows}
      value={typeof value === 'string' ? value : ''}
      onChange={(event) => onChange(event.target.value)}
    />
  )
}
