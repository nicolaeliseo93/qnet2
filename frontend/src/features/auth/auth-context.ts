import { createContext } from 'react'
import type { LoginPayload, User } from '@/features/auth/types'

export interface AuthContextValue {
  user: User | null
  isAuthenticated: boolean
  /** True while the persisted token is being validated on first load. */
  isInitializing: boolean
  login: (payload: LoginPayload) => Promise<void>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)
