import { useRef } from 'react'

export interface OpportunityNameAutofill {
  /** True while the name field has not been hand-edited (still safe to auto-compute from the chosen product categories). */
  isAuto: () => boolean
  /** Marks the name as hand-edited: permanently disables the auto-compute for the rest of this form session (AC-107). */
  disable: () => void
}

/**
 * Tracks whether the opportunity name is still in "auto-compute from the
 * chosen product categories" mode (spec 0040 amendment rev.3, AC-107). A
 * plain ref, not `useState`: read/written only inside event handlers (the
 * name input's `onChange`, the product-lines add/remove actions, the lead
 * picker's select/clear), never during render — flipping it never needs to
 * trigger a re-render by itself, only the `setValue('name', ...)` call that
 * follows does.
 *
 * CREATE starts auto (the name is empty and safe to derive); EDIT starts
 * already disabled — the loaded name is authoritative and must never be
 * silently overwritten by touching the product lines.
 */
export function useOpportunityNameAutofill(startsDisabled: boolean): OpportunityNameAutofill {
  const disabledRef = useRef(startsDisabled)
  return {
    isAuto: () => !disabledRef.current,
    disable: () => {
      disabledRef.current = true
    },
  }
}
