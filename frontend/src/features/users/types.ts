/**
 * Users CRUD types. The generic table types (columns/filters/actions/rows) live
 * in `features/table/types.ts`; this file holds only what is genuinely
 * users-specific — the user resource and its create/update payloads.
 * Source of truth: the frozen Users CRUD API contract.
 */

import type { PersonalDataCard } from '@/features/personal-data/types'
import type { PersonalDataPayload } from '@/features/personal-data/drafts'

/**
 * Allowed UI/user locales. Kept as a defensive guard only — the selectable
 * locales are served by the backend (GET /api/config → enums.locale) and the
 * form renders them from there, never from this literal.
 */
export type UserLocale = 'en' | 'it'

/** A role membership as returned by the backend ({id, name}). */
export interface UserRole {
  id: number
  name: string
}

/**
 * Single user detail returned by GET/POST/PATCH /users (envelope `data`).
 * Matches UserResource in the frozen API contract.
 */
export interface UserDetail {
  id: number
  name: string
  email: string
  locale: UserLocale
  /** Role memberships as {id, name}: the form picks by id, the detail shows name. */
  roles: UserRole[]
  /** Absolute URL to the authenticated avatar download endpoint, or null. */
  avatar_url: string | null
  /**
   * The user's personal-data card (with its contacts and addresses), present only
   * when the backend loaded it (whenLoaded). Absent/null when the user has none.
   */
  personal_data?: PersonalDataCard | null
  created_at: string | null
}

/** Payload for POST /users (create). `password` is required here. */
export interface CreateUserPayload {
  email: string
  locale: UserLocale
  /** Role IDS to assign (for-select standard, ADR 0011). */
  roles?: number[]
  password: string
  password_confirmation: string
  /**
   * The nested personal-data card written atomically with the user (ADR 0012).
   * Required: `users.name` is derived server-side from this card (the user form
   * has no `name` field), so identity must always be present on create.
   */
  personal_data: PersonalDataPayload
}

/**
 * Payload for PATCH /users/{id} (partial update). Every field is optional so
 * the request only carries what actually changed.
 */
export interface UpdateUserPayload {
  email?: string
  locale?: UserLocale
  /** Role IDS to assign (for-select standard, ADR 0011). */
  roles?: number[]
  password?: string
  password_confirmation?: string
  /**
   * Optional nested profile written atomically with the user (ADR 0012). Omit to
   * leave the card untouched; present = card upsert plus authoritative child sync.
   */
  personal_data?: PersonalDataPayload
}
