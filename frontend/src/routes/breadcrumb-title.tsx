/* eslint-disable react-refresh/only-export-components -- context module: the provider and its paired hooks are one cohesive unit, not a route/page component */
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'

/**
 * Human-readable labels for URL segments that carry no meaning on their own —
 * an entity id (`/registries/12`) is the only case today. A detail/edit page
 * registers the entity's name for its own path while mounted; the breadcrumb
 * (and the page title) then render "Registries / Acme S.p.A." instead of
 * "Registries / 12".
 *
 * Keyed by crumb href (not by "the last crumb") so `/registries/12/edit` also
 * resolves its middle crumb, whose href is exactly the one the edit page
 * registers.
 */
interface BreadcrumbTitleContextValue {
  titles: Record<string, string>
  setTitle: (href: string, label: string | null) => void
}

const BreadcrumbTitleContext = createContext<BreadcrumbTitleContextValue | null>(null)

/** Hoisted so the no-provider path keeps a stable identity across renders. */
const EMPTY_TITLES: Record<string, string> = {}

export function BreadcrumbTitleProvider({ children }: { children: ReactNode }) {
  const [titles, setTitles] = useState<Record<string, string>>({})

  // `setTitle` MUST keep a stable identity: it is a dependency of the consumer's
  // registration effect, so a new identity per update would re-run that effect
  // (unregister + register) forever.
  const setTitle = useCallback((href: string, label: string | null) => {
    setTitles((current) => {
      if (label === null) {
        if (!(href in current)) return current
        const next = { ...current }
        delete next[href]
        return next
      }
      if (current[href] === label) return current
      return { ...current, [href]: label }
    })
  }, [])

  const value = useMemo<BreadcrumbTitleContextValue>(
    () => ({ titles, setTitle }),
    [titles, setTitle],
  )

  return <BreadcrumbTitleContext.Provider value={value}>{children}</BreadcrumbTitleContext.Provider>
}

/**
 * Registers `label` as the breadcrumb title of `href` for as long as the caller
 * is mounted (an entity name is only known once its detail has been fetched, so
 * `label` is `undefined` while loading). No-op outside the provider, so a page
 * stays renderable in isolation (tests, storybook).
 */
export function useBreadcrumbTitle(href: string, label: string | null | undefined): void {
  const context = useContext(BreadcrumbTitleContext)
  const setTitle = context?.setTitle

  useEffect(() => {
    if (!setTitle) return
    setTitle(href, label ?? null)
    return () => setTitle(href, null)
  }, [href, label, setTitle])
}

/** Registered breadcrumb titles, keyed by crumb href. Empty outside the provider. */
export function useBreadcrumbTitles(): Record<string, string> {
  return useContext(BreadcrumbTitleContext)?.titles ?? EMPTY_TITLES
}
