import type { ReactNode } from 'react'
import { FieldHint } from '@/components/field-hint'
import { FormLabel } from '@/components/ui/form'

interface HintLabelProps {
  children: ReactNode
  /** Advanced-field explanation shown in a tooltip beside the label. */
  hint?: string
  /** Accessible name of the tooltip trigger. */
  hintLabel?: string
}

/**
 * A `FormLabel` paired with an optional {@link FieldHint} tooltip, laid out as
 * siblings (never nesting the info button inside the `<label>`). Used by the
 * definition's advanced sub-forms (type config, validation) where a field needs
 * an on-demand explanation; primary identity fields use an always-visible inline
 * helper instead.
 */
export function HintLabel({ children, hint, hintLabel }: HintLabelProps) {
  if (!hint) {
    return <FormLabel>{children}</FormLabel>
  }
  return (
    <div className="flex items-center gap-1.5">
      <FormLabel>{children}</FormLabel>
      <FieldHint text={hint} label={hintLabel ?? ''} />
    </div>
  )
}
