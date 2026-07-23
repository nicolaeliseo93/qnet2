import type { FocusEvent } from 'react'

/** The slice of a React Hook Form field this helper needs — no RHF generics to thread. */
interface FormattableField {
  onChange: (value: string) => void
  onBlur: () => void
}

/**
 * Re-writes a text field into its canonical shape when it loses focus, then
 * hands over to the form's own blur handler (touched state, validation).
 *
 * Formatting while TYPING would fight the caret (a space swallowed mid-word,
 * a letter re-cased under the cursor), so it happens once, on blur — the same
 * moment the field is validated. The server re-applies it anyway
 * (`App\Support\InputFormat`); this only makes the stored shape visible
 * immediately instead of after the next refetch.
 */
export function formatOnBlur(
  field: FormattableField,
  format: (value: string) => string,
) {
  return (event: FocusEvent<HTMLInputElement>): void => {
    const formatted = format(event.target.value)

    if (formatted !== event.target.value) {
      field.onChange(formatted)
    }

    field.onBlur()
  }
}
