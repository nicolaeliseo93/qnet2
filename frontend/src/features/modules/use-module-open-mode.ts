import { useAuth } from '@/features/auth/use-auth'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import { resolveOpenMode } from '@/features/modules/resolve-open-mode'
import { OPEN_MODE_MODAL, type OpenMode } from '@/features/modules/types'

/**
 * Resolves how `domain` opens RIGHT NOW for the authenticated user (spec
 * 0042): the user's `module_open_preferences` (already loaded once as part
 * of `['auth','me']`, no extra fetch) combined with the module's registered
 * `defaultMode` via `resolveOpenMode`. A domain that isn't (yet) registered
 * falls back to `'modal'` — today's universal native mount for every module
 * not yet in the registry.
 */
export function useModuleOpenMode(domain: string): OpenMode {
  const { user } = useAuth()
  const entry = getModuleRegistryEntry(domain)
  const defaultMode = entry?.defaultMode ?? OPEN_MODE_MODAL

  return resolveOpenMode(user?.module_open_preferences, domain, defaultMode)
}
