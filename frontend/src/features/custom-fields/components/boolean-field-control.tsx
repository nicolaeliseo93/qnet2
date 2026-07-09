import { Checkbox } from '@/components/ui/checkbox'
import { Switch } from '@/components/ui/switch'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'

/**
 * `type: 'boolean'` → `Checkbox` or `Switch` per `config.display`. Neither
 * Radix primitive has a native `readOnly`, so a readonly field is rendered
 * disabled (matches the pattern already used for `product_type`'s `Select`).
 */
export function BooleanFieldControl({
  descriptor,
  value,
  onChange,
  disabled,
  readOnly,
  id,
  describedBy,
  invalid,
}: CustomFieldControlProps) {
  const checked = value === true
  const isDisabled = disabled || readOnly

  if (descriptor.config?.display === 'switch') {
    return (
      <Switch
        id={id}
        checked={checked}
        disabled={isDisabled}
        aria-describedby={describedBy}
        aria-invalid={invalid}
        onCheckedChange={(next) => onChange(next === true)}
      />
    )
  }

  return (
    <Checkbox
      id={id}
      checked={checked}
      disabled={isDisabled}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      onCheckedChange={(next) => onChange(next === true)}
    />
  )
}
