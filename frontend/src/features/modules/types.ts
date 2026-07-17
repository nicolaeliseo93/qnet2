import type { ComponentType } from 'react'

/** Where a module's screens (view/create/edit) are mounted. */
export const OPEN_MODE_MODAL = 'modal' as const
export const OPEN_MODE_PAGE = 'page' as const

export type OpenMode = typeof OPEN_MODE_MODAL | typeof OPEN_MODE_PAGE

/** The 3-state preference a user picks in settings (spec 0042). */
export const PREFERENCE_MODE_MODAL = 'modal' as const
export const PREFERENCE_MODE_PAGE = 'page' as const
export const PREFERENCE_MODE_CUSTOM = 'custom' as const

export type ModuleOpenPreferenceMode =
  | typeof PREFERENCE_MODE_MODAL
  | typeof PREFERENCE_MODE_PAGE
  | typeof PREFERENCE_MODE_CUSTOM

/**
 * Per-user preference, persisted as a single JSON column on `users` and
 * exposed/saved via GET/PATCH `/auth/me` (spec 0042). `overrides` is only
 * consulted when `mode === 'custom'`; it stays intact (never trimmed) when
 * switching to `'modal'`/`'page'` so no per-module choice is silently lost
 * (AC-016).
 */
export interface ModuleOpenPreferences {
  mode: ModuleOpenPreferenceMode
  overrides: Record<string, OpenMode>
}

/** Server contract: a null column serializes to this (never `null` on the wire). */
export const DEFAULT_MODULE_OPEN_PREFERENCES: ModuleOpenPreferences = {
  mode: PREFERENCE_MODE_CUSTOM,
  overrides: {},
}

/** Which entity (create vs edit) a `FormScreen` renders. */
export type ModuleFormScreenMode = { type: 'create' } | { type: 'edit'; id: number }

export interface ModuleDetailScreenProps {
  id: number
}

export interface ModuleFormScreenProps {
  mode: ModuleFormScreenMode
  /** Called after a successful create/update with the saved entity's id. */
  onSuccess: (id: number) => void
  onCancel: () => void
}

/**
 * One registrable module (spec 0042). `domain` is the same kebab-case slug
 * used by `config/tables.php`/`TableRegistry` server-side. `DetailScreen`/
 * `FormScreen` are content-only adapters (fetch + the module's existing
 * presentational view/form, no page chrome): reused as-is inside the modal
 * Sheet (`useModuleOpener`) and inside the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`), which own the page chrome around
 * them. The module's list page (`${basePath}`) is NOT part of the registry:
 * AC-012 only requires `new`/`:id`/`:id/edit` to be generated, so the
 * existing list route/page stays declared manually in `router.tsx`, same as
 * every other module not yet in the registry.
 */
export interface ModuleRegistryEntry {
  domain: string
  basePath: string
  /** The module's native mount today — preserved bit-for-bit when the user has no preference (AC-010). */
  defaultMode: OpenMode
  /** i18n key under `navigation.*`. */
  labelKey: string
  DetailScreen: ComponentType<ModuleDetailScreenProps>
  FormScreen: ComponentType<ModuleFormScreenProps>
  /**
   * Optional extra actions rendered as a real child component in the generic
   * detail page's header, between "Back" and "Edit" (e.g. leads' "Create/Go
   * to opportunity" CTA) — mounted via JSX so its own hooks run correctly,
   * never called as a plain function. Any fetch it performs shares the same
   * detail query key/cache as `DetailScreen`, so React Query dedupes it
   * instead of firing a second request. Omitted by every module with no such
   * extra.
   */
  DetailPageActions?: ComponentType<ModuleDetailScreenProps>
}
