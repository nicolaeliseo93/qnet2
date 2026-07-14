import { createContext, useContext } from 'react'

/**
 * Nesting depth of quick-create Dialogs opened from a relation field's "+"
 * (spec 0028). `0` = the field lives in the page's own form. The relation
 * field components increment it by one for the "+" they render and stop
 * rendering their own "+" once the depth is already `> 0`, so a form mounted
 * inside its own quick-create Dialog can never open a second one on top of
 * it — the simplest way to guarantee the non-goal "nested quick-create must
 * never crash" without touching `QuickCreateButton` itself.
 */
export const QuickCreateDepthContext = createContext(0)

export function useQuickCreateDepth(): number {
  return useContext(QuickCreateDepthContext)
}
