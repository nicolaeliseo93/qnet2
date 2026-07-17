import {
  OPEN_MODE_MODAL,
  OPEN_MODE_PAGE,
  PREFERENCE_MODE_MODAL,
  PREFERENCE_MODE_PAGE,
  type ModuleOpenPreferences,
  type OpenMode,
} from '@/features/modules/types'

/**
 * Single source of truth for "how does this module open right now" (spec
 * 0042). Pure and side-effect free: `mode: 'modal'|'page'` short-circuits to
 * that mode for every module; `'custom'` falls back to `domain`'s override,
 * or to `defaultMode` when none is set. A missing preference (the user never
 * touched the setting) resolves to `defaultMode`, so every module opens
 * exactly as it does today (AC-010).
 */
export function resolveOpenMode(
  prefs: ModuleOpenPreferences | null | undefined,
  domain: string,
  defaultMode: OpenMode,
): OpenMode {
  if (!prefs) {
    return defaultMode
  }

  if (prefs.mode === PREFERENCE_MODE_MODAL) {
    return OPEN_MODE_MODAL
  }

  if (prefs.mode === PREFERENCE_MODE_PAGE) {
    return OPEN_MODE_PAGE
  }

  // prefs.mode === PREFERENCE_MODE_CUSTOM
  return prefs.overrides[domain] ?? defaultMode
}
