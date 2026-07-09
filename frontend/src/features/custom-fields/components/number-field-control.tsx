import { Input } from '@/components/ui/input'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'

/** `type: 'integer' | 'decimal'` → a numeric `Input`, bounded by `config.min`/`config.max`/`config.step`. */
export function NumberFieldControl({
  descriptor,
  value,
  onChange,
  disabled,
  readOnly,
  id,
  describedBy,
  invalid,
}: CustomFieldControlProps) {
  const isInteger = descriptor.type === 'integer'
  const step = descriptor.config?.step ?? (isInteger ? 1 : 'any')

  return (
    <Input
      id={id}
      type="number"
      disabled={disabled}
      readOnly={readOnly}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      placeholder={descriptor.placeholder ?? undefined}
      min={descriptor.config?.min}
      max={descriptor.config?.max}
      step={step}
      value={typeof value === 'number' ? value : ''}
      onChange={(event) => {
        const raw = event.target.value
        if (raw === '') {
          onChange(null)
          return
        }
        onChange(isInteger ? Number.parseInt(raw, 10) : Number.parseFloat(raw))
      }}
    />
  )
}
