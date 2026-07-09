import { Input } from '@/components/ui/input'
import type { CustomFieldControlProps } from '@/features/custom-fields/components/custom-field-control-props'

/**
 * Factory for the string-backed scalar controls added on top of the MVP
 * (date/datetime/time/email/url/color): each is a controlled `<Input>` with a
 * native HTML `type`, differing only by that type. Called once per field type
 * while the component registry object is built (module scope), so every
 * returned component has a stable identity across renders — never define these
 * inside another component (frontend.md §10).
 */
export function createNativeInputFieldControl(htmlType: string) {
  return function NativeInputFieldControl({
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
        type={htmlType}
        disabled={disabled}
        readOnly={readOnly}
        aria-describedby={describedBy}
        aria-invalid={invalid}
        placeholder={descriptor.placeholder ?? undefined}
        value={typeof value === 'string' ? value : ''}
        onChange={(event) => onChange(event.target.value)}
      />
    )
  }
}
