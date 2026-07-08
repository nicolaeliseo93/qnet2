import type { CustomFieldDescriptor, CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Uniform controlled-component contract every entry of the
 * `field-component-registry` implements. Keeping it in its own module (rather
 * than colocated with the registry) lets each field component import just the
 * type, not the registry itself.
 */
export interface CustomFieldControlProps {
  descriptor: CustomFieldDescriptor
  value: CustomFieldValue
  onChange: (value: CustomFieldValue) => void
  /** Hard-disabled or non-editable for the current role (forwarded from `MetaField`). */
  disabled: boolean
  /** Editable=false but not disabled (forwarded from `MetaField`); only text-like inputs render this as native `readOnly`. */
  readOnly: boolean
  /**
   * Accessible-error triad (frontend.md §10), read from `useFormField()` by
   * `CustomFieldsSection` and threaded down here because `<FormControl>`'s
   * automatic `Slot` id injection only reaches its immediate JSX child — one
   * level too shallow for a registry-dispatched control. Components whose
   * underlying primitive does not accept arbitrary DOM/ARIA props (the async
   * paginated selects, the plain multi-select) fall back to `aria-label`
   * only for the accessible name; documented in their own file.
   */
  id: string
  describedBy: string
  invalid: boolean
}
