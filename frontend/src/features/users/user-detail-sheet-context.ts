import { createContext, useContext } from 'react'

export interface UserDetailSheetContextValue {
  /** Opens the shared read-only user detail Sheet for the given user id. */
  openUserDetail: (userId: number) => void
}

/**
 * The opener context, split from the provider module on purpose: user cells
 * (rendered in every grid) depend only on this tiny hook, NOT on the heavy
 * `UserDetailView` graph the provider mounts. A no-op default lets a cell
 * rendered outside the provider degrade to "no modal" instead of throwing.
 */
export const UserDetailSheetContext = createContext<UserDetailSheetContextValue>({
  openUserDetail: () => {},
})

/** Access the shared user-detail opener from any table cell (or elsewhere). */
export function useUserDetailSheet(): UserDetailSheetContextValue {
  return useContext(UserDetailSheetContext)
}
