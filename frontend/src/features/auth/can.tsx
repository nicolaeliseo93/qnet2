import type { ReactNode } from 'react'
import { useAbilities } from '@/features/auth/use-abilities'

interface CanProps {
  /** Permission name required to render the children, e.g. "users.view". */
  permission: string
  children: ReactNode
  /** Optional fallback rendered when the permission is missing. */
  fallback?: ReactNode
  /**
   * Rendered while abilities are still loading, before the permission is known.
   * Defaults to `null` so the fallback never flashes during the initial fetch.
   */
  loading?: ReactNode
}

/**
 * Conditionally renders UI based on the current user's permissions.
 * UX gate only — backend Policies remain the source of truth.
 */
export function Can({ permission, children, fallback = null, loading = null }: CanProps) {
  const { can, isLoading } = useAbilities()
  // Wait for abilities to resolve; otherwise `can()` is false during the fetch
  // and the fallback ("non hai i permessi") flashes before the real content.
  if (isLoading) return <>{loading}</>
  return <>{can(permission) ? children : fallback}</>
}
