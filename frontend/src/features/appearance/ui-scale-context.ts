import { createContext, useContext } from 'react'

export interface UiScaleContextValue {
  /** Current slider value (0..100). */
  scale: number
  /** Derived multiplier (0.8..1.3) for pixel-based subsystems (AG Grid). */
  factor: number
  /** Apply a new value live (drag preview). Persistence is the caller's job. */
  setScale: (scale: number) => void
}

export const UiScaleContext = createContext<UiScaleContextValue | null>(null)

/**
 * Read the applied UI scale. Split from the provider so lightweight consumers
 * (the data-table on every grid) depend only on this hook, not the provider's
 * effect graph.
 */
export function useUiScale(): UiScaleContextValue {
  const context = useContext(UiScaleContext)
  if (!context) {
    throw new Error('useUiScale must be used within a UiScaleProvider.')
  }
  return context
}
