import { useCallback, useState } from 'react'

const STORAGE_KEY_PREFIX = 'stats-panel:'
const OPEN_VALUE = 'true'
const DEFAULT_OPEN = false

function storageKey(domain: string): string {
  return `${STORAGE_KEY_PREFIX}${domain}`
}

/**
 * DOM id of a module's statistics panel. Shared by the panel (`id`) and by the
 * toggle button (`aria-controls`) so the two can never drift apart (AC-006).
 */
export function statsPanelId(domain: string): string {
  return `stats-panel-${domain}`
}

function readStoredOpen(domain: string): boolean {
  if (typeof window === 'undefined') {
    return DEFAULT_OPEN
  }
  try {
    return window.localStorage.getItem(storageKey(domain)) === OPEN_VALUE
  } catch {
    return DEFAULT_OPEN
  }
}

/**
 * Open/closed state of a module's statistics panel, persisted per domain
 * (AC-008). Closed by default; an unavailable localStorage (private mode,
 * quota) degrades to a session-only toggle instead of crashing.
 */
export function useStatsPanel(domain: string) {
  const [isOpen, setIsOpen] = useState<boolean>(() => readStoredOpen(domain))

  const toggle = useCallback(() => {
    const next = !isOpen
    setIsOpen(next)
    try {
      window.localStorage.setItem(storageKey(domain), String(next))
    } catch {
      // Storage can be unavailable: the toggle still works for this session.
    }
  }, [domain, isOpen])

  return { isOpen, toggle }
}
